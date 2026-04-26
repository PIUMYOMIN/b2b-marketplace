<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'product_sku',
        'variant_sku',
        'selected_options',
        'quantity_unit',
        'price',
        'quantity',
        'subtotal',
        'product_data',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'quantity'         => 'decimal:3',
        'subtotal'         => 'decimal:2',
        'selected_options' => 'array',
        'product_data'     => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
     * Build a snapshot of the product + variant at time of order.
     * Call this before saving the order item.
     */
    public static function buildSnapshot(Product $product, ?ProductVariant $variant): array
    {
        return [
            'product_id'       => $product->id,
            'name_en'          => $product->name_en,
            'name_mm'          => $product->name_mm,
            'sku'              => $product->sku,
            'brand'            => $product->brand,
            'images'           => $product->images,
            'variant_id'       => $variant?->id,
            'variant_sku'      => $variant?->sku,
            'variant_price'    => $variant?->price,
            'variant_unit'     => $variant?->effectiveUnit(),
            'variant_options'  => $variant?->optionValues->map(fn($v) => [
                'option' => $v->option->name,
                'label'  => $v->label,
                'value'  => $v->value,
            ]),
        ];
    }
}
