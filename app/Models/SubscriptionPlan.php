<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_mmk',
        'billing_cycle',
        'product_limit',
        'commission_rate',
        'analytics_enabled',
        'bulk_import_enabled',
        'priority_support',
        'custom_storefront',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_mmk'           => 'decimal:2',
        'commission_rate'     => 'decimal:4',
        'analytics_enabled'   => 'boolean',
        'bulk_import_enabled' => 'boolean',
        'priority_support'    => 'boolean',
        'custom_storefront'   => 'boolean',
        'is_active'           => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** True when this plan allows unlimited products. */
    public function isUnlimited(): bool
    {
        return $this->product_limit === -1;
    }

    /** Human-readable commission string, e.g. "5%" */
    public function getCommissionPercentAttribute(): string
    {
        return number_format($this->commission_rate * 100, 0) . '%';
    }

    /** Human-readable product limit, e.g. "20" or "Unlimited" */
    public function getProductLimitLabelAttribute(): string
    {
        return $this->isUnlimited() ? 'Unlimited' : (string) $this->product_limit;
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function subscriptions()
    {
        return $this->hasMany(SellerSubscription::class, 'plan_id');
    }
}