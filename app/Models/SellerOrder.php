<?php
// app/Models/SellerOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id', 'seller_id', 'order_number',
        'subtotal_amount', 'shipping_fee', 'tax_amount',
        'commission_amount', 'total_amount',
        'delivery_method', 'status', 'payment_method',
        'zone_matched', 'zone_name', 'fee_source',
        'confirmed_at', 'shipped_at', 'delivered_at',
        'cancelled_at', 'seller_notes',
    ];

    protected $casts = [
        'subtotal_amount'   => 'decimal:2',
        'shipping_fee'      => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'zone_matched'      => 'boolean',
        'confirmed_at'      => 'datetime',
        'shipped_at'        => 'datetime',
        'delivered_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items(): HasMany
    {
        // Order items scoped to this seller
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id')
            ->whereHas('product', fn($q) => $q->where('seller_id', $this->seller_id));
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'order_id', 'order_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'order_id', 'order_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Generate a seller-specific sub-order number.
     * Parent order PY-2026-001 → PY-2026-001-A, PY-2026-001-B, etc.
     */
    public static function generateNumber(string $parentOrderNumber, int $index): string
    {
        $suffix = chr(ord('A') + $index); // A, B, C …
        return "{$parentOrderNumber}-{$suffix}";
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isConfirmed(): bool  { return $this->status === 'confirmed'; }
    public function isDelivered(): bool  { return $this->status === 'delivered'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
}