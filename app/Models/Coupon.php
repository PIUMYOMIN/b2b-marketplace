<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Coupon — a buyer-entered code at checkout created by a seller.
 *
 * Different from Discount, which is a price reduction a seller applies
 * directly to a product (no code entry by the buyer required).
 *
 * Coupons are always scoped to the seller who created them and optionally
 * restricted to specific products from that seller.
 */
class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'name',
        'code',
        'type',
        'value',
        'min_order_amount',
        'applicable_product_ids',
        'max_uses',
        'used_count',
        'max_uses_per_user',
        'is_active',
        'is_one_time_use',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'applicable_product_ids' => 'array',
        'is_active' => 'boolean',
        'is_one_time_use' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'seller_id' => 'integer',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'max_uses_per_user' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    /**
     * Return a human-readable validation error string, or null if the coupon is valid.
     *
     * Replaces the old boolean isValid() which caused the controller to emit
     * "This coupon is no longer valid" for every failure — including "not started yet",
     * which is a completely different situation from "expired".
     */
    public function getValidationError(): ?string
    {
        if (!$this->is_active) {
            return 'This coupon is inactive.';
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            // Format in the server timezone so the message is meaningful to the user
            $startsFormatted = $this->starts_at->format('M j, Y');
            return "This coupon is not active yet. It becomes available on {$startsFormatted}.";
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return 'This coupon has expired.';
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }

        return null; // valid
    }

    /**
     * @deprecated Use getValidationError() === null instead.
     * Kept for any callers outside the validate endpoint.
     */
    public function isValid(): bool
    {
        return $this->getValidationError() === null;
    }

    /**
     * Check whether a specific buyer has exhausted their per-user limit.
     */
    public function hasUserExhausted(int $userId): bool
    {
        if ($this->is_one_time_use || $this->max_uses_per_user) {
            $limit = $this->is_one_time_use ? 1 : $this->max_uses_per_user;
            $used = $this->usages()->where('user_id', $userId)->count();
            return $used >= $limit;
        }
        return false;
    }

    /**
     * Check whether this coupon applies to a given product.
     *
     * A coupon always belongs to one seller. If applicable_product_ids is null,
     * it applies to ALL products from that seller. Otherwise only those listed.
     */
    public function appliesToProduct(Product $product): bool
    {
        // Product must belong to the coupon's seller
        if ((int) $product->seller_id !== $this->seller_id) {
            return false;
        }

        // No restriction → applies to all seller products
        if (empty($this->applicable_product_ids)) {
            return true;
        }

        return in_array($product->id, $this->applicable_product_ids, true);
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percentage') {
            return round($subtotal * ($this->value / 100), 2);
        }

        // Fixed — never discount more than the order total
        return min((float) $this->value, $subtotal);
    }

    /**
     * Record one use of this coupon.
     */
    public function recordUsage(int $userId, int $orderId, float $discountAmount): void
    {
        $this->usages()->create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
        ]);

        $this->increment('used_count');
    }
}
