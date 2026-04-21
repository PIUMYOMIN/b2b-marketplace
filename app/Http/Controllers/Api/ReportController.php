<?php
// app/Http/Controllers/Api/ReportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportComment;
use App\Notifications\ReportStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Rules\Recaptcha;

class ReportController extends Controller
{
    // ── USER: Submit a report ──────────────────────────────────────────────────
    /**
     * POST /reports
     * Open to authenticated users AND guests (for fraud/safety reports).
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'category'           => 'required|in:bug,payment,order,seller,product,account,content,billing,delivery,safety,suggestion,other',
            'subject'            => 'required|string|min:5|max:200',
            'description'        => 'required|string|min:20|max:5000',
            'priority'           => 'nullable|in:low,medium,high,critical',
            'related_order_id'   => 'nullable|integer|exists:orders,id',
            'related_seller_id'  => 'nullable|integer|exists:users,id',
            'related_product_id' => 'nullable|integer',
            'related_url'        => 'nullable|url|max:500',
            'recaptcha_token'    => ['required', new Recaptcha],
            'attachments'        => 'nullable|array|max:5',
            'attachments.*'      => 'file|max:5120|mimes:jpg,jpeg,png,pdf,mp4,mov',
            // Guest fields (required when not authenticated)
            'guest_name'         => 'nullable|string|max:100',
            'guest_email'        => 'nullable|email',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $user     = $request->user();
        $priority = $v->validated()['priority'] ?? 'medium';
        $category = $v->validated()['category'];

        // Auto-escalate priority for critical categories
        $priority = Report::autoEscalatePriority($category, $priority);

        // Handle file attachments
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("reports/attachments", 'public');
                $attachmentPaths[] = [
                    'path'     => $path,
                    'name'     => $file->getClientOriginalName(),
                    'size'     => $file->getSize(),
                    'mime'     => $file->getMimeType(),
                ];
            }
        }

        $report = Report::create([
            'ticket_id'          => Report::generateTicketId(),
            'reporter_id'        => $user?->id,
            'guest_name'         => $user ? null : $v->validated()['guest_name'] ?? null,
            'guest_email'        => $user ? null : $v->validated()['guest_email'] ?? null,
            'category'           => $category,
            'priority'           => $priority,
            'subject'            => $v->validated()['subject'],
            'description'        => $v->validated()['description'],
            'attachments'        => $attachmentPaths ?: null,
            'related_order_id'   => $v->validated()['related_order_id'] ?? null,
            'related_seller_id'  => $v->validated()['related_seller_id'] ?? null,
            'related_product_id' => $v->validated()['related_product_id'] ?? null,
            'related_url'        => $v->validated()['related_url'] ?? null,
            'status'             => Report::STATUS_OPEN,
            'reporter_ip'        => $request->ip(),
            'reporter_locale'    => $request->header('Accept-Language', 'en'),
        ]);

        // System comment for audit trail
        ReportComment::create([
            'report_id'   => $report->id,
            'user_id'     => null,
            'body'        => "Report filed. Ticket ID: {$report->ticket_id}",
            'author_type' => 'system',
            'is_internal' => true,
        ]);

        // Notify reporter by email
        $this->notifyReporter($report, 'created');

        return response()->json([
            'success'   => true,
            'ticket_id' => $report->ticket_id,
            'message'   => "Your report has been submitted. Track it with ticket ID: {$report->ticket_id}",
            'data'      => $this->formatReport($report, false),
        ], 201);
    }

    // ── USER: My reports ───────────────────────────────────────────────────────
    /**
     * GET /reports
     */
    public function index(Request $request)
    {
        $user    = $request->user();
        $reports = Report::where('reporter_id', $user->id)
            ->with(['comments' => fn($q) => $q->where('is_internal', false)])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $reports->through(fn($r) => $this->formatReport($r, false)),
        ]);
    }

    /**
     * GET /reports/{ticket_id}
     */
    public function show(Request $request, string $ticketId)
    {
        $user   = $request->user();
        $report = Report::where('ticket_id', $ticketId)
            ->where('reporter_id', $user->id)
            ->with('publicComments.author')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatReport($report, false),
        ]);
    }

    // ── USER: Add a reply ──────────────────────────────────────────────────────
    /**
     * POST /reports/{ticket_id}/comments
     */
    public function addComment(Request $request, string $ticketId)
    {
        $user   = $request->user();
        $report = Report::where('ticket_id', $ticketId)
            ->where('reporter_id', $user->id)
            ->firstOrFail();

        if ($report->isResolved()) {
            return response()->json(['success' => false, 'message' => 'This ticket is already closed.'], 422);
        }

        $v = Validator::make($request->all(), [
            'body'           => 'required|string|min:5|max:2000',
            'attachments'    => 'nullable|array|max:3',
            'attachments.*'  => 'file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $comment = ReportComment::create([
            'report_id'   => $report->id,
            'user_id'     => $user->id,
            'body'        => $v->validated()['body'],
            'author_type' => 'reporter',
            'is_internal' => false,
        ]);

        // Reopen if waiting
        if ($report->status === Report::STATUS_WAITING) {
            $report->update(['status' => Report::STATUS_IN_REVIEW]);
        }

        return response()->json(['success' => true, 'data' => $comment]);
    }

    // ── ADMIN: List all reports ────────────────────────────────────────────────
    /**
     * GET /admin/reports
     */
    public function adminIndex(Request $request)
    {
        $q = Report::with(['reporter:id,name,email', 'assignee:id,name'])
            ->orderByRaw("FIELD(priority,'critical','high','medium','low')")
            ->orderByRaw("FIELD(status,'open','in_review','waiting','resolved','closed','rejected')")
            ->orderByDesc('created_at');

        if ($request->status)   $q->where('status', $request->status);
        if ($request->category) $q->where('category', $request->category);
        if ($request->priority) $q->where('priority', $request->priority);
        if ($request->search)   $q->where(function($sq) use ($request) {
            $sq->where('ticket_id', 'like', "%{$request->search}%")
               ->orWhere('subject',  'like', "%{$request->search}%");
        });
        if ($request->assigned_to === 'me') {
            $q->where('assigned_to', $request->user()->id);
        } elseif ($request->assigned_to === 'unassigned') {
            $q->whereNull('assigned_to');
        }

        $reports = $q->paginate(20);

        // Summary counts for dashboard
        $summary = [
            'open'      => Report::whereIn('status', [Report::STATUS_OPEN])->count(),
            'in_review' => Report::where('status', Report::STATUS_IN_REVIEW)->count(),
            'critical'  => Report::where('priority', Report::PRIORITY_CRITICAL)
                ->whereNotIn('status', [Report::STATUS_RESOLVED, Report::STATUS_CLOSED, Report::STATUS_REJECTED])
                ->count(),
            'sla_breached' => Report::whereIn('status', [Report::STATUS_OPEN, Report::STATUS_IN_REVIEW])
                ->whereNull('first_response_at')
                ->get()
                ->filter(fn($r) => $r->isSlaBreached())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => $reports->through(fn($r) => $this->formatReport($r, true)),
        ]);
    }

    /**
     * GET /admin/reports/{ticket_id}
     */
    public function adminShow(string $ticketId)
    {
        $report = Report::where('ticket_id', $ticketId)
            ->with(['reporter:id,name,email,type', 'assignee:id,name', 'comments.author:id,name,type'])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->formatReport($report, true)]);
    }

    /**
     * PATCH /admin/reports/{ticket_id}
     * Update status, priority, assignment, resolution.
     */
    public function adminUpdate(Request $request, string $ticketId)
    {
        $admin  = $request->user();
        $report = Report::where('ticket_id', $ticketId)->firstOrFail();

        $v = Validator::make($request->all(), [
            'status'      => 'nullable|in:open,in_review,waiting,resolved,closed,rejected',
            'priority'    => 'nullable|in:low,medium,high,critical',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'admin_notes' => 'nullable|string|max:2000',
            'resolution'  => 'nullable|string|max:1000',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $changes  = [];
        $oldStatus = $report->status;
        $data      = [];

        if ($request->has('status') && $v->validated()['status'] !== $report->status) {
            $newStatus = $v->validated()['status'];
            $data['status'] = $newStatus;
            if (in_array($newStatus, [Report::STATUS_RESOLVED, Report::STATUS_CLOSED]) && !$report->resolved_at) {
                $data['resolved_at'] = now();
            }
            $changes[] = "Status changed from {$report->status} to {$newStatus}";
        }

        if ($request->has('priority') && $v->validated()['priority'] !== $report->priority) {
            $data['priority'] = $v->validated()['priority'];
            $changes[] = "Priority set to {$v->validated()['priority']}";
        }

        if ($request->has('assigned_to')) {
            $data['assigned_to'] = $v->validated()['assigned_to'];
            $data['assigned_at'] = now();
            if (!$report->first_response_at) {
                $data['first_response_at'] = now();
            }
            $changes[] = "Assigned to admin #{$v->validated()['assigned_to']}";
        }

        if ($request->has('admin_notes')) $data['admin_notes'] = $v->validated()['admin_notes'];
        if ($request->has('resolution'))  $data['resolution']  = $v->validated()['resolution'];

        $report->update($data);

        // Audit comment
        if (!empty($changes)) {
            ReportComment::create([
                'report_id'   => $report->id,
                'user_id'     => $admin->id,
                'body'        => implode('. ', $changes) . '.',
                'author_type' => 'admin',
                'is_internal' => true,
            ]);
        }

        // Notify reporter if status changed
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->notifyReporter($report->fresh(), 'status_changed', $v->validated()['resolution'] ?? null);
        }

        return response()->json(['success' => true, 'data' => $this->formatReport($report->fresh(), true)]);
    }

    /**
     * POST /admin/reports/{ticket_id}/comments
     * Admin reply (can be internal note or public reply).
     */
    public function adminComment(Request $request, string $ticketId)
    {
        $admin  = $request->user();
        $report = Report::where('ticket_id', $ticketId)->firstOrFail();

        $v = Validator::make($request->all(), [
            'body'        => 'required|string|min:2|max:3000',
            'is_internal' => 'boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $isInternal = $v->validated()['is_internal'] ?? false;

        $comment = ReportComment::create([
            'report_id'   => $report->id,
            'user_id'     => $admin->id,
            'body'        => $v->validated()['body'],
            'author_type' => 'admin',
            'is_internal' => $isInternal,
        ]);

        // Record first response time
        if (!$report->first_response_at && !$isInternal) {
            $report->update(['first_response_at' => now()]);
        }

        // If public reply, set status to waiting (for reporter)
        if (!$isInternal && $report->status === Report::STATUS_IN_REVIEW) {
            $report->update(['status' => Report::STATUS_WAITING]);
        }

        // Notify reporter of public replies
        if (!$isInternal) {
            $this->notifyReporter($report, 'admin_replied', $comment->body);
        }

        return response()->json(['success' => true, 'data' => $comment]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function formatReport(Report $report, bool $isAdmin): array
    {
        $base = [
            'id'          => $report->id,
            'ticket_id'   => $report->ticket_id,
            'category'    => $report->category,
            'priority'    => $report->priority,
            'subject'     => $report->subject,
            'description' => $report->description,
            'status'      => $report->status,
            'resolution'  => $report->resolution,
            'attachments' => $report->attachments,
            'related_url' => $report->related_url,
            'created_at'  => $report->created_at?->toIso8601String(),
            'updated_at'  => $report->updated_at?->toIso8601String(),
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'comments'    => $report->publicComments?->map(fn($c) => [
                'id'          => $c->id,
                'body'        => $c->body,
                'author_type' => $c->author_type,
                'author_name' => $c->author?->name ?? ($c->author_type === 'system' ? 'System' : 'Support Team'),
                'created_at'  => $c->created_at->toIso8601String(),
            ])->values(),
            'sla_hours'    => Report::slaHours($report->priority),
            'sla_breached' => $report->isSlaBreached(),
        ];

        if ($isAdmin) {
            $base['reporter']         = $report->reporter ? [
                'id'    => $report->reporter->id,
                'name'  => $report->reporter->name,
                'email' => $report->reporter->email,
                'type'  => $report->reporter->type,
            ] : ['name' => $report->guest_name, 'email' => $report->guest_email];
            $base['assignee']         = $report->assignee?->only('id', 'name');
            $base['admin_notes']      = $report->admin_notes;
            $base['reporter_ip']      = $report->reporter_ip;
            $base['reporter_locale']  = $report->reporter_locale;
            $base['first_response_at']= $report->first_response_at?->toIso8601String();
            // Show all comments (including internal) to admin
            $base['comments'] = $report->comments?->map(fn($c) => [
                'id'          => $c->id,
                'body'        => $c->body,
                'author_type' => $c->author_type,
                'author_name' => $c->author?->name ?? 'System',
                'is_internal' => $c->is_internal,
                'created_at'  => $c->created_at->toIso8601String(),
            ])->values();
        }

        return $base;
    }

    private function notifyReporter(Report $report, string $event, ?string $extra = null): void
    {
        $email = $report->reporter?->email ?? $report->guest_email;
        if (!$email) return;

        try {
            $name = $report->reporter?->name ?? $report->guest_name ?? 'User';
            $subject = match($event) {
                'created'        => "[{$report->ticket_id}] Your report has been received",
                'status_changed' => "[{$report->ticket_id}] Update on your report — " . ucfirst(str_replace('_', ' ', $report->status)),
                'admin_replied'  => "[{$report->ticket_id}] Support team replied to your report",
                default          => "[{$report->ticket_id}] Report update",
            };

            \Mail::send('emails.report_notification', [
                'name'      => $name,
                'ticket_id' => $report->ticket_id,
                'subject'   => $report->subject,
                'status'    => $report->status,
                'event'     => $event,
                'extra'     => $extra,
                'url'       => config('app.frontend_url') . "/my-reports/{$report->ticket_id}",
            ], function($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error("Report notification failed: " . $e->getMessage());
        }
    }
}