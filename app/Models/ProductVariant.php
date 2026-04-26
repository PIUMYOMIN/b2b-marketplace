<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'quantity',
        'quantity_unit',
        'moq',
        'image',
        'position',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'quantity'  => 'decimal:3',
        'moq'       => 'integer',
        'position'  => 'integer',
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The option values that define this variant combination.
     * e.g. [Red, M] or [Blue, L]
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_values',
            'variant_id',
            'option_value_id'
        )->with('option');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Business Logic Helpers
    // -------------------------------------------------------------------------

    /**
     * Effective MOQ: uses this variant's moq if set,
     * otherwise falls back to the parent product's moq.
     */
    public function effectiveMoq(): int
    {
        return $this->moq ?? $this->product->moq ?? 1;
    }

    /**
     * Effective unit: uses this variant's unit if set,
     * otherwise falls back to the parent product's unit.
     */
    public function effectiveUnit(): string
    {
        return $this->quantity_unit ?? $this->product->quantity_unit ?? 'piece';
    }

    /**
     * Whether this variant has stock available.
     */
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Reduce stock by a given quantity.
     * Throws an exception if stock would go negative.
     */
    public function deductStock(float $qty): void
    {
        if ($this->quantity < $qty) {
            throw new \RuntimeException(
                "Insufficient stock for variant [{$this->sku}]. Available: {$this->quantity}"
            );
        }

        $this->decrement('quantity', $qty);
    }

    /**
     * Returns a human-readable label for this variant.
     * e.g. "Red / M" or "Blue / XL"
     */
    public function label(): string
    {
        return $this->optionValues
            ->pluck('label')
            ->join(' / ');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }
}
