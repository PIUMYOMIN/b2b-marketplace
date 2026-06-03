<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_mm',
        'slug_en',
        'slug_mm',
        'description_en',
        'description_mm',
        'product_type',
        'price',
        'quantity',
        'category_id',
        'seller_id',
        'average_rating',
        'review_count',
        'specifications',
        'images',
        'dimensions',
        'sku',
        'barcode',
        'brand',
        'model',
        'material',
        'origin',
        'discount_price',
        'discount_type',
        'discount_percentage',
        'sale_badge',
        'compare_at_price',
        'sale_quantity',
        'sale_sold',
        'discount_start',
        'discount_end',
        'is_on_sale',
        'views',
        'sales',
        'is_featured',
        'is_new',
        'condition',
        'weight_kg',
        'warranty',
        'warranty_type',
        'warranty_period',
        'warranty_conditions',
        'return_policy',
        'return_conditions',
        'shipping_details',
        'shipping_cost',
        'shipping_time',
        'shipping_origin',
        'customs_info',
        'hs_code',
        'quantity_unit',
        'moq',
        'quantity_step',
        'min_order_unit',
        'lead_time',
        'packaging_details',
        'additional_info',
        'file_url',
        'file_type',
        'file_size',
        'listed_at',
        'approved_at',
        'rejection_reason',
        'status',
        'is_active',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'quantity'            => 'decimal:3',
        'discount_price'      => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'compare_at_price'    => 'decimal:2',
        'shipping_cost'       => 'decimal:2',
        'average_rating'      => 'decimal:2',
        'specifications'      => 'array',
        'images'              => 'array',
        'dimensions'          => 'array',
        'is_on_sale'          => 'boolean',
        'is_featured'         => 'boolean',
        'is_new'              => 'boolean',
        'is_active'           => 'boolean',
        'discount_start'      => 'date',
        'discount_end'        => 'date',
        'listed_at'           => 'datetime',
        'approved_at'         => 'datetime',
        'moq'                 => 'integer',
        'quantity_step'       => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('position');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    // -------------------------------------------------------------------------
    // Business Logic Helpers
    // -------------------------------------------------------------------------

    /**
     * Whether this product uses the variant system (has defined options).
     */
    public function hasVariants(): bool
    {
        return $this->options()->exists();
    }

    /**
     * Total available stock across active variants, or product-level stock
     * for simple physical products with no active variants.
     * Returns null for digital / service products (no stock tracking).
     */
    public function totalStock(): ?float
    {
        if ($this->product_type !== 'physical') {
            return null;
        }

        if ($this->activeVariants()->exists()) {
            return (float) $this->activeVariants()->sum('quantity');
        }

        return (float) ($this->quantity ?? 0);
    }

    /**
     * Whether the product has any stock available.
     */
    public function isInStock(): bool
    {
        if ($this->product_type !== 'physical') {
            return true; // Digital/service always available
        }

        if ($this->activeVariants()->exists()) {
            return $this->activeVariants()->where('quantity', '>', 0)->exists();
        }

        return (float) ($this->quantity ?? 0) > 0;
    }

    /**
     * The lowest price among active variants.
     * Falls back to the base product price if no variants exist.
     */
    public function lowestVariantPrice(): float
    {
        $min = $this->activeVariants()->min('price');
        return $min ?? (float) $this->price;
    }

/**
     * Returns the effective quantity unit for display.
     * Reads from the product-level unit (variants can override per-variant).
     */
    public function effectiveUnit(): string
    {
        return $this->quantity_unit ?? 'piece';
    }

    /**
     * The effective quantity step for this product.
     * Defaults to 1 (no step restriction) when not set.
     */
    public function effectiveMoq(): int
    {
        return max(1, (int) ($this->moq ?? 1));
    }

    /**
     * The effective quantity step for this product.
     * Falls back to MOQ so valid quantities are MOQ, 2x MOQ, 3x MOQ...
     */
    public function effectiveStep(): int
    {
        $moq = $this->effectiveMoq();
        $step = (int) ($this->quantity_step ?? $moq);

        return $step > 1 ? $step : $moq;
    }

    /**
     * Validate that a given quantity satisfies MOQ and step rules.
     * Returns null on pass, or an error message string on failure.
     */
    public function validateMoqStep(int|float $quantity): ?string
    {
        $moq  = $this->effectiveMoq();
        $step = $this->effectiveStep();
        $unit = $this->effectiveUnit();

        if ($quantity < $moq) {
            return "Minimum order quantity for \"{$this->name_en}\" is {$moq} {$unit}(s).";
        }

        if ($step > 1) {
            $remainder = fmod($quantity - $moq, $step);
            if (abs($remainder) > 0.0001) {
                $nextValid = $moq + (ceil(($quantity - $moq) / $step) * $step);
                return "Quantity for \"{$this->name_en}\" must be in steps of {$step}. "
                    . "Next valid quantity: {$nextValid}.";
            }
        }

        return null;
    }

    /**
     * Wholesale pricing tiers for this product (not scoped to a variant).
     */
    public function wholesaleTiers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductWholesaleTier::class)
            ->whereNull('variant_id');
    }

    /**
     * Resolve the best per-unit price for a given quantity, applying
     * wholesale tiers when the quantity meets a threshold.
     *
     * @param  float $quantity
     * @return array{price: float, tier: ProductWholesaleTier|null}
     */
    public function resolveWholesalePrice(float $quantity): array
    {
        $basePrice = (float) $this->price;
        $tiers     = $this->wholesaleTiers()->where('is_active', true)->orderBy('min_qty')->get();

        if ($tiers->isEmpty()) {
            return ['price' => $basePrice, 'tier' => null];
        }

        $matched = $tiers->sortByDesc('min_qty')->first(fn($t) => $quantity >= $t->min_qty);

        return $matched
            ? ['price' => (float) $matched->price_per_unit, 'tier' => $matched]
            : ['price' => $basePrice, 'tier' => null];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')->where('is_active', true);
    }

    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('product_type', $type);
    }

    /**
     * Whether the product currently has an active, valid sale.
     *
     * Checks:
     *   1. is_on_sale flag is true
     *   2. A discount_price or discount_percentage is actually set
     *   3. Today falls within discount_start / discount_end (if set)
     */
    public function isCurrentlyOnSale(): bool
    {
        if (!$this->is_on_sale) {
            return false;
        }
 
        // Must have an actual discount value
        if (!$this->discount_price && !$this->discount_percentage) {
            return false;
        }
 
        $today = now()->startOfDay();
 
        if ($this->discount_start && $this->discount_start->gt($today)) {
            return false;
        }
 
        if ($this->discount_end && $this->discount_end->lt($today)) {
            return false;
        }
 
        return true;
    }
 
    /**
     * The price the buyer actually pays.
     *
     * - When on sale with a discount_price:       returns discount_price
     * - When on sale with a discount_percentage:  calculates from base price
     * - Otherwise:                                returns the base price
     */
    public function getSellingPrice(): float
    {
        if (!$this->isCurrentlyOnSale()) {
            return (float) $this->price;
        }
 
        if ($this->discount_price && (float) $this->discount_price < (float) $this->price) {
            return (float) $this->discount_price;
        }
 
        if ($this->discount_percentage > 0) {
            return round((float) $this->price * (1 - (float) $this->discount_percentage / 100), 2);
        }
 
        return (float) $this->price;
    }
 
    /**
     * Amount saved vs the base price. 0 when not on sale.
     */
    public function getDiscountSaved(): float
    {
        return max(0, round((float) $this->price - $this->getSellingPrice(), 2));
    }
 
    /**
     * Effective discount percentage for badge display.
     * Calculates from prices when discount_percentage is not stored explicitly.
     */
    public function getEffectiveDiscountPercentage(): float
    {
        if (!$this->isCurrentlyOnSale() || (float) $this->price <= 0) {
            return 0.0;
        }
 
        if ($this->discount_percentage > 0) {
            return (float) $this->discount_percentage;
        }
 
        // Derive from prices
        $selling = $this->getSellingPrice();
        return round(((float) $this->price - $selling) / (float) $this->price * 100, 1);
    }
}
