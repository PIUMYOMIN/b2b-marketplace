<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWholesaleTier extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'min_qty',
        'price_per_unit',
        'discount_pct',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_qty'        => 'integer',
        'price_per_unit' => 'decimal:2',
        'discount_pct'   => 'decimal:2',
        'sort_order'     => 'integer',
        'is_active'      => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('min_qty');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recalculate discount_pct relative to the parent product's base price.
     * Call this before saving when price_per_unit changes.
     */
    public function recalculateDiscount(): void
    {
        $basePrice = $this->product?->price;
        if ($basePrice && $basePrice > 0) {
            $this->discount_pct = round((1 - ($this->price_per_unit / $basePrice)) * 100, 2);
        }
    }
}