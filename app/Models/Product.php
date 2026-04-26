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
     * Total available stock across all active variants.
     * Returns null for digital / service products (no stock tracking).
     */
    public function totalStock(): ?float
    {
        if ($this->product_type !== 'physical') {
            return null;
        }

        return $this->activeVariants()->sum('quantity');
    }

    /**
     * Whether the product has any stock available.
     */
    public function isInStock(): bool
    {
        if ($this->product_type !== 'physical') {
            return true; // Digital/service always available
        }

        return $this->activeVariants()->where('quantity', '>', 0)->exists();
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
}
