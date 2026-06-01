<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SellerSubscription extends Model
{
    // ── Mass-assignable ───────────────────────────────────────────────────

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'next_billing_at',
        'amount_paid_mmk',
        'payment_reference',
        'payment_method',
        'changed_by',
        'notes',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────

    protected $casts = [
        'starts_at'       => 'date',
        'ends_at'         => 'date',
        'next_billing_at' => 'date',
        'amount_paid_mmk' => 'decimal:2',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Only subscriptions that are currently usable:
     * status = 'active'  AND  (ends_at IS NULL  OR  ends_at >= today).
     *
     * Used everywhere we resolve a seller's plan at runtime, including in
     * CommissionRateResolver, SellerProfile::subscription(), and the
     * SubscriptionController helpers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(function ($q) {
                         $q->whereNull('ends_at')
                           ->orWhere('ends_at', '>=', Carbon::today());
                     });
    }

    /**
     * Filter by a specific plan slug — useful for admin reports.
     *   SellerSubscription::forPlan('professional')->count()
     */
    public function scopeForPlan($query, string $slug)
    {
        return $query->whereHas('plan', fn ($p) => $p->where('slug', $slug));
    }

    /**
     * Subscriptions that have passed their ends_at date but are still marked active —
     * used by the ExpireSubscriptions console command.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
                     ->whereNotNull('ends_at')
                     ->where('ends_at', '<', Carbon::today());
    }

    // ── Computed attributes ───────────────────────────────────────────────

    /**
     * How many calendar days remain on this subscription.
     * Returns null for the free (indefinite) plan.
     * Returns 0 if already expired.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->ends_at) {
            return null; // free / indefinite
        }

        $diff = Carbon::today()->diffInDays($this->ends_at, false);

        return max(0, (int) $diff);
    }

    /**
     * True when ends_at is in the past (regardless of status column).
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->ends_at !== null && $this->ends_at->lt(Carbon::today());
    }

    /**
     * True when the subscription is on a paid plan and will expire within 7 days.
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        $remaining = $this->days_remaining;
        return $remaining !== null && $remaining <= 7 && $remaining > 0;
    }

    /**
     * Human-readable status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->is_expired) {
            return 'Expired';
        }

        return match ($this->status) {
            'active'          => 'Active',
            'cancelled'       => 'Cancelled',
            'expired'         => 'Expired',
            'pending_payment' => 'Pending Payment',
            default           => ucfirst($this->status),
        };
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /** The plan this subscription is for. */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /** The seller (User) who owns this subscription. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The admin/user who manually changed this subscription (nullable). */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
