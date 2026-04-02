<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'content', 'type', 'image',
        'cta_label', 'cta_url', 'cta_style',
        'badge_label', 'badge_color',
        'target_audience', 'is_active', 'show_once',
        'delay_seconds', 'starts_at', 'ends_at', 'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'show_once'   => 'boolean',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
    ];

    /** Active and within date window */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Filter by target audience */
    public function scopeForAudience($query, ?string $type = null)
    {
        // 'all' shows to everyone; specific types only show to matching users
        return $query->where(fn ($q) =>
            $q->where('target_audience', 'all')
              ->orWhere('target_audience', $type ?? 'all')
        );
    }
}