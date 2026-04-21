<?php
// app/Models/Report.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Report extends Model
{
    use SoftDeletes;

    // ── Status constants ───────────────────────────────────────────────────────
    const STATUS_OPEN      = 'open';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_WAITING   = 'waiting';
    const STATUS_RESOLVED  = 'resolved';
    const STATUS_CLOSED    = 'closed';
    const STATUS_REJECTED  = 'rejected';

    // ── Priority constants ─────────────────────────────────────────────────────
    const PRIORITY_LOW      = 'low';
    const PRIORITY_MEDIUM   = 'medium';
    const PRIORITY_HIGH     = 'high';
    const PRIORITY_CRITICAL = 'critical';

    // Categories that auto-escalate to HIGH priority
    const AUTO_ESCALATE_CATEGORIES = ['fraud', 'safety', 'payment'];

    protected $fillable = [
        'ticket_id', 'reporter_id', 'guest_name', 'guest_email',
        'category', 'priority', 'subject', 'description', 'attachments',
        'related_order_id', 'related_seller_id', 'related_product_id', 'related_url',
        'status', 'assigned_to', 'assigned_at', 'admin_notes', 'resolution',
        'reporter_ip', 'reporter_locale',
        'first_response_at', 'resolved_at', 'closed_at', 'duplicate_of',
    ];

    protected $casts = [
        'attachments'        => 'array',
        'assigned_at'        => 'datetime',
        'first_response_at'  => 'datetime',
        'resolved_at'        => 'datetime',
        'closed_at'          => 'datetime',
    ];

    // ── Ticket ID generation ───────────────────────────────────────────────────
    /**
     * Generate unique human-readable ticket ID: RPT-2026-00001
     */
    public static function generateTicketId(): string
    {
        $year   = date('Y');
        $prefix = "RPT-{$year}-";
        $last   = static::where('ticket_id', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('ticket_id');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    // ── Relationships ──────────────────────────────────────────────────────────
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedOrder()
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function relatedSeller()
    {
        return $this->belongsTo(User::class, 'related_seller_id');
    }

    public function comments()
    {
        return $this->hasMany(ReportComment::class)->orderBy('created_at');
    }

    public function publicComments()
    {
        return $this->hasMany(ReportComment::class)
            ->where('is_internal', false)
            ->orderBy('created_at');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────
    public function scopeOpen($q)        { return $q->where('status', self::STATUS_OPEN); }
    public function scopeInReview($q)    { return $q->where('status', self::STATUS_IN_REVIEW); }
    public function scopeResolved($q)    { return $q->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]); }
    public function scopeCritical($q)    { return $q->where('priority', self::PRIORITY_CRITICAL); }
    public function scopeUnassigned($q)  { return $q->whereNull('assigned_to'); }

    // ── Helpers ────────────────────────────────────────────────────────────────
    public function isOpen(): bool     { return $this->status === self::STATUS_OPEN; }
    public function isResolved(): bool { return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED]); }

    /**
     * Auto-escalate priority for critical categories.
     */
    public static function autoEscalatePriority(string $category, string $currentPriority): string
    {
        if (in_array($category, ['safety', 'fraud']) && $currentPriority === self::PRIORITY_LOW) {
            return self::PRIORITY_HIGH;
        }
        if ($category === 'payment' && $currentPriority === self::PRIORITY_LOW) {
            return self::PRIORITY_MEDIUM;
        }
        return $currentPriority;
    }

    /**
     * First-response SLA in hours by priority.
     */
    public static function slaHours(string $priority): int
    {
        return match($priority) {
            self::PRIORITY_CRITICAL => 4,
            self::PRIORITY_HIGH     => 12,
            self::PRIORITY_MEDIUM   => 48,
            default                 => 120,   // low: 5 days
        };
    }

    /**
     * Check if SLA is breached (no first response within target window).
     */
    public function isSlaBreached(): bool
    {
        if ($this->first_response_at) return false;
        return $this->created_at->diffInHours(now()) > self::slaHours($this->priority);
    }
}