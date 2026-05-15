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
        'quantity_step',
        'image',
        'position',
        'is_active',
    ];

    protected $casts = [
        'product_id'    => 'integer',
        'price'         => 'decimal:2',
        'quantity'      => 'decimal:3',
        'moq'           => 'integer',
        'quantity_step' => 'integer',
        'position'      => 'integer',
        'is_active'     => 'boolean',
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
    /**
     * The effective quantity step for this variant.
     * Falls back to the parent product's step, then to 1.
     * e.g. variant step=5, MOQ=10 → valid quantities: 10, 15, 20 …
     */
    public function effectiveStep(): int
    {
        return $this->quantity_step ?? $this->product?->quantity_step ?? 1;
    }

    /**
     * Validate that a given quantity satisfies MOQ and step rules for this variant.
     * Returns null on pass, or an error message string on failure.
     */
    public function validateMoqStep(int|float $quantity): ?string
    {
        $moq   = $this->effectiveMoq();
        $step  = $this->effectiveStep();
        $unit  = $this->effectiveUnit();
        $label = $this->product->name_en ?? 'this product';

        if ($quantity < $moq) {
            return "Minimum order quantity for \"{$label}\" is {$moq} {$unit}(s).";
        }

        if ($step > 1) {
            $remainder = fmod($quantity - $moq, $step);
            if (abs($remainder) > 0.0001) {
                $nextValid = $moq + (ceil(($quantity - $moq) / $step) * $step);
                return "Quantity for \"{$label}\" must be in steps of {$step}. "
                    . "Next valid quantity: {$nextValid}.";
            }
        }

        return null;
    }

    /**
     * Wholesale tiers for this specific variant.
     * Note: resolveWholesalePrice() queries across both variant-scoped and
     * product-level tiers. This relationship is variant-scoped only.
     */
    public function wholesaleTiers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductWholesaleTier::class, 'variant_id');
    }

    /**
     * Resolve the best per-unit price for a given quantity.
     * Variant-scoped tiers take precedence over product-level tiers.
     *
     * @param  float $quantity
     * @return array{price: float, tier: ProductWholesaleTier|null}
     */
    public function resolveWholesalePrice(float $quantity): array
    {
        $basePrice = (float) $this->price;

        // 1. Variant-scoped tiers
        $tiers = ProductWholesaleTier::where('product_id', $this->product_id)
            ->where(function ($q) {
                $q->where('variant_id', $this->id)->orWhereNull('variant_id');
            })
            ->where('is_active', true)
            ->orderBy('min_qty')
            ->get();

        if ($tiers->isEmpty()) {
            return ['price' => $basePrice, 'tier' => null];
        }

        $matched = $tiers->sortByDesc('min_qty')->first(fn($t) => $quantity >= $t->min_qty);

        return $matched
            ? ['price' => (float) $matched->price_per_unit, 'tier' => $matched]
            : ['price' => $basePrice, 'tier' => null];

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