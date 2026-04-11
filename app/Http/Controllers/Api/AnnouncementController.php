<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    /** GET /announcements — public, returns active announcements for current visitor */
    public function index(Request $request)
    {
        $userType = null;
        if ($request->bearerToken()) {
            try {
                $user = Auth::guard('sanctum')->user();
                $userType = $user?->type;
            } catch (\Throwable) {}
        }

        $announcements = Announcement::active()
            ->forAudience($userType)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => $this->format($a));

        return response()->json([
            'success' => true,
            'data'    => $announcements,
        ]);
    }

    /** GET /admin/announcements — full list for admin management */
    public function adminIndex()
    {
        $announcements = Announcement::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => $this->format($a));

        return response()->json(['success' => true, 'data' => $announcements]);
    }

    /** POST /admin/announcements */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'content'         => 'nullable|string|max:2000',
            'type'            => 'required|in:announcement,promotion,newsletter,advertisement,sponsorship',
            'display_style'   => 'nullable|in:popup_card,popup_banner,page_banner',
            'banner_link_url' => 'nullable|string|max:500',
            'banner_aspect_ratio' => 'nullable|in:16:9,4:3,3:1,1:1',
            'image'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'cta_label'       => 'nullable|string|max:80',
            'cta_url'         => 'nullable|string|max:500',
            'cta_style'       => 'nullable|in:primary,outline',
            'badge_label'     => 'nullable|string|max:40',
            'badge_color'     => 'nullable|in:green,red,blue,yellow,purple,orange',
            'target_audience' => 'nullable|in:all,guests,buyers,sellers',
            'is_active'       => 'boolean',
            'show_once'       => 'boolean',
            'delay_seconds'   => 'nullable|integer|min:0|max:30',
            'starts_at'       => 'nullable|date',
            'ends_at'         => 'nullable|date|after_or_equal:starts_at',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('announcements', 'public');
        }

        $announcement = Announcement::create($data);

        return response()->json([
            'success' => true,
            'data'    => $this->format($announcement),
            'message' => 'Announcement created.',
        ], 201);
    }

    /** PUT /admin/announcements/{id} */
    public function update(Request $request, int $id)
    {
        $announcement = Announcement::findOrFail($id);

        $v = Validator::make($request->all(), [
            'title'           => 'sometimes|required|string|max:255',
            'content'         => 'nullable|string|max:2000',
            'type'            => 'sometimes|in:announcement,promotion,newsletter,advertisement,sponsorship',
            'display_style'   => 'nullable|in:popup_card,popup_banner,page_banner',
            'banner_link_url' => 'nullable|string|max:500',
            'banner_aspect_ratio' => 'nullable|in:16:9,4:3,3:1,1:1',
            'image'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'cta_label'       => 'nullable|string|max:80',
            'cta_url'         => 'nullable|string|max:500',
            'cta_style'       => 'nullable|in:primary,outline',
            'badge_label'     => 'nullable|string|max:40',
            'badge_color'     => 'nullable|in:green,red,blue,yellow,purple,orange',
            'target_audience' => 'nullable|in:all,guests,buyers,sellers',
            'is_active'       => 'boolean',
            'show_once'       => 'boolean',
            'delay_seconds'   => 'nullable|integer|min:0|max:30',
            'starts_at'       => 'nullable|date',
            'ends_at'         => 'nullable|date',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        if ($request->hasFile('image')) {
            if ($announcement->image) Storage::disk('public')->delete($announcement->image);
            $data['image'] = $request->file('image')->store('announcements', 'public');
        } elseif ($request->input('remove_image') == '1' && $announcement->image) {
            Storage::disk('public')->delete($announcement->image);
            $data['image'] = null;
        }

        $announcement->update($data);

        return response()->json([
            'success' => true,
            'data'    => $this->format($announcement->fresh()),
            'message' => 'Announcement updated.',
        ]);
    }

    /** DELETE /admin/announcements/{id} */
    public function destroy(int $id)
    {
        $announcement = Announcement::findOrFail($id);
        if ($announcement->image) Storage::disk('public')->delete($announcement->image);
        $announcement->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /** PATCH /admin/announcements/{id}/toggle */
    public function toggle(int $id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->update(['is_active' => !$announcement->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $announcement->is_active,
        ]);
    }

    private function format(Announcement $a): array
    {
        return [
            'id'              => $a->id,
            'title'           => $a->title,
            'content'         => $a->content,
            'type'            => $a->type,
            'image'           => $a->image ? asset('storage/' . $a->image) : null,
            'cta_label'       => $a->cta_label,
            'cta_url'         => $a->cta_url,
            'cta_style'       => $a->cta_style,
            'badge_label'     => $a->badge_label,
            'badge_color'     => $a->badge_color,
            'target_audience' => $a->target_audience,
            'is_active'       => $a->is_active,
            'show_once'       => $a->show_once,
            'delay_seconds'   => $a->delay_seconds,
            'starts_at'       => $a->starts_at?->toIso8601String(),
            'ends_at'         => $a->ends_at?->toIso8601String(),
            'sort_order'      => $a->sort_order,
            'created_at'      => $a->created_at->toIso8601String(),
        ];
    }
}