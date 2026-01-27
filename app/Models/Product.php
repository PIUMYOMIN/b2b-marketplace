<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'price',
        'discount_price', // Updated from discount_amount
        'discount_type',
        'discount_percentage',
        'sale_badge',
        'compare_at_price',
        'sale_quantity',
        'sale_sold',
        'discount_start',
        'discount_end',
        'is_on_sale',
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
        'color',
        'material',
        'origin',
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
        'min_order_unit',
        'moq',
        'lead_time',
        'packaging_details',
        'additional_info',
        'listed_at',
        'approved_at',
        'status',
        'is_active'
    ];

    protected $casts = [
        'specifications' => 'array',
        'images' => 'array',
        'dimensions' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_on_sale' => 'boolean',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'discount_start' => 'date',
        'discount_end' => 'date',
        'listed_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the seller that owns the product.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Check if product is currently on sale
     */
    public function getIsCurrentlyOnSaleAttribute(): bool
    {
        if (!$this->is_on_sale) {
            return false;
        }

        $now = now();

        // Check if sale has started
        if ($this->discount_start && $this->discount_start->gt($now)) {
            return false;
        }

        // Check if sale has ended
        if ($this->discount_end && $this->discount_end->lt($now)) {
            return false;
        }

        // Check sale quantity limit
        if ($this->sale_quantity && $this->sale_sold >= $this->sale_quantity) {
            return false;
        }

        return true;
    }

    /**
     * Get current selling price (with discount applied)
     */
    public function getSellingPriceAttribute(): float
    {
        if (!$this->isCurrentlyOnSale) {
            return (float) $this->price;
        }

        if ($this->discount_price && $this->discount_price > 0) {
            return (float) $this->discount_price;
        }

        if ($this->discount_percentage && $this->discount_percentage > 0) {
            $discountAmount = $this->price * ($this->discount_percentage / 100);
            return (float) ($this->price - $discountAmount);
        }

        return (float) $this->price;
    }

    /**
     * Get discount amount saved
     */
    public function getDiscountSavedAttribute(): float
    {
        if (!$this->isCurrentlyOnSale) {
            return 0.0;
        }

        return (float) ($this->price - $this->selling_price);
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute(): float
    {
        if (!$this->isCurrentlyOnSale || $this->price <= 0) {
            return 0.0;
        }

        if ($this->attributes['discount_percentage']) {
            return (float) $this->attributes['discount_percentage'];
        }

        $saved = $this->discount_saved;
        if ($saved <= 0) {
            return 0.0;
        }

        return (float) round(($saved / $this->price) * 100, 2);
    }

    /**
     * Get sale ends in days
     */
    public function getSaleEndsInAttribute(): ?int
    {
        if (!$this->isCurrentlyOnSale || !$this->discount_end) {
            return null;
        }

        return now()->diffInDays($this->discount_end, false);
    }

    /**
     * Scope for products currently on sale
     */
    public function scopeOnSale($query)
    {
        return $query->where('is_on_sale', true)
            ->where(function ($q) {
                $q->whereNull('discount_start')
                    ->orWhere('discount_start', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('discount_end')
                    ->orWhere('discount_end', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('sale_quantity')
                    ->orWhereRaw('sale_sold < sale_quantity');
            });
    }

    /**
     * Get name attribute (fallback to English if Myanmar not available)
     */
    public function getNameAttribute(): string
    {
        return $this->name_en ?? $this->name_mm ?? '';
    }

    /**
     * Get description attribute (fallback to English if Myanmar not available)
     */
    public function getDescriptionAttribute(): string
    {
        return $this->description_en ?? $this->description_mm ?? '';
    }

    /**
     * Get the primary image URL.
     */
    public function getPrimaryImageAttribute(): ?string
    {
        if (empty($this->images)) {
            return null;
        }

        // Find primary image or return first
        foreach ($this->images as $image) {
            if (isset($image['is_primary']) && $image['is_primary']) {
                return $image['url'];
            }
        }

        return $this->images[0]['url'] ?? null;
    }

    /**
     * Get all image URLs.
     */
    public function getImageUrlsAttribute(): array
    {
        if (empty($this->images)) {
            return [];
        }

        return array_map(function ($image) {
            return $image['url'];
        }, $this->images);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include products from a specific category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to only include products from a specific seller.
     */
    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope a query to search products.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name_en', 'like', "%{$searchTerm}%")
                ->orWhere('name_mm', 'like', "%{$searchTerm}%")
                ->orWhere('description_en', 'like', "%{$searchTerm}%")
                ->orWhere('description_mm', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Check if product is out of stock.
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity <= 0;
    }

    /**
     * Check if product is low in stock.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity > 0 && $this->quantity <= 10;
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0) . ' MMK';
    }

    /**
     * Get formatted selling price.
     */
    public function getFormattedSellingPriceAttribute(): string
    {
        return number_format($this->selling_price, 0) . ' MMK';
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot('quantity', 'price', 'seller_id')
            ->withTimestamps();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    //seller profile relationship
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id', 'user_id');
    }

    /**
     * Increment sale sold count
     */
    public function incrementSaleSold(int $quantity = 1): bool
    {
        if ($this->sale_quantity && ($this->sale_sold + $quantity) > $this->sale_quantity) {
            return false;
        }

        $this->increment('sale_sold', $quantity);
        return true;
    }

    public function getAverageRatingAttribute($value)
    {
        return $value ?? 0;
    }

    public function getReviewCountAttribute($value)
    {
        return $value ?? 0;
    }
}