<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'variant_id',
        'selected_options',
        'quantity',
        'quantity_unit',
        'price',
        'product_data',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'price'            => 'decimal:2',
        'selected_options' => 'array',
        'product_data'     => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * The effective MOQ the buyer must meet for this cart item.
     */
    public function effectiveMoq(): int
    {
        return $this->variant?->effectiveMoq() ?? $this->product?->moq ?? 1;
    }

    /**
     * Subtotal for this cart item.
     */
    public function subtotal(): float
    {
        return round((float) $this->price * (float) $this->quantity, 2);
    }

    /**
     * Refresh the locked price from the current variant / product price.
     * Call before checkout to detect price changes since adding to cart.
     */
    public function refreshPrice(): void
    {
        $livePrice = $this->variant?->price ?? $this->product?->price;

        if ($livePrice && (float) $livePrice !== (float) $this->price) {
            $this->update(['price' => $livePrice]);
        }
    }
}
