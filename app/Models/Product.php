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
        'discount_price',
        'discount_start',
        'discount_end',
        'views',
        'sales',
        'is_featured',
        'is_new',
        'condition',
        'is_on_sale',
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
}
