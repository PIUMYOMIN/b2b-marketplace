<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'applicable_to',
        'applicable_product_ids',
        'applicable_category_ids',
        'applicable_seller_ids',
        'max_uses_per_user',
        'is_one_time_use',
        'created_by'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_one_time_use' => 'boolean',
        'applicable_product_ids' => 'array',
        'applicable_category_ids' => 'array',
        'applicable_seller_ids' => 'array',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usages()
    {
        return $this->hasMany(DiscountUsage::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'discount_product');
    }

    public function isApplicableToProduct($productId, $categoryId, $sellerId)
    {
        if (!$this->is_active) {
            return false;
        }

        if (now()->lt($this->starts_at) || now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        switch ($this->applicable_to) {
            case 'all_products':
                return true;

            case 'specific_products':
                return in_array($productId, $this->applicable_product_ids ?? []);

            case 'specific_categories':
                return in_array($categoryId, $this->applicable_category_ids ?? []);

            case 'specific_sellers':
                return in_array($sellerId, $this->applicable_seller_ids ?? []);

            default:
                return false;
        }
    }

    public function calculateDiscount($originalPrice)
    {
        switch ($this->type) {
            case 'percentage':
                return $originalPrice * ($this->value / 100);

            case 'fixed':
                return min($this->value, $originalPrice);

            case 'free_shipping':
                return 0; // Special case for free shipping
        }
    }
}
