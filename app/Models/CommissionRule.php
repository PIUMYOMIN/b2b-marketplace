<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CommissionRule extends Model
{
    protected $fillable = [
        'type',
        'reference_id',
        'reference_label',
        'rate',
        'min_rate',
        'max_rate',
        'is_active',
        'valid_from',
        'valid_until',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'min_rate' => 'decimal:4',
        'max_rate' => 'decimal:4',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** Only rules that are active and within their validity window today. */
    public function scopeActive(Builder $q): Builder
    {
        $today = Carbon::today();
        return $q->where('is_active', true)
            ->where(fn($s) => $s->whereNull('valid_from')
                ->orWhere('valid_from', '<=', $today))
            ->where(fn($s) => $s->whereNull('valid_until')
                ->orWhere('valid_until', '>=', $today));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Human-readable percentage string, e.g. "5.00%" */
    public function getPercentageAttribute(): string
    {
        return number_format((float) $this->rate * 100, 2) . '%';
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}