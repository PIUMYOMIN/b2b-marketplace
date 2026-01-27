<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryArea extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_profile_id',
        'user_id',
        'area_type',
        'country',
        'state',
        'city',
        'township',
        'specific_location',
        'postal_code',
        'is_deliverable',
        'shipping_fee',
        'free_shipping_threshold',
        'estimated_delivery_days_min',
        'estimated_delivery_days_max',
        'standard_shipping_available',
        'express_shipping_available',
        'pickup_available',
        'pickup_location',
        'has_weight_limit',
        'max_weight_kg',
        'has_size_limit',
        'size_restrictions',
        'product_category_restrictions',
        'excluded_dates',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_deliverable' => 'boolean',
        'shipping_fee' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'standard_shipping_available' => 'boolean',
        'express_shipping_available' => 'boolean',
        'pickup_available' => 'boolean',
        'has_weight_limit' => 'boolean',
        'max_weight_kg' => 'decimal:2',
        'has_size_limit' => 'boolean',
        'size_restrictions' => 'array',
        'product_category_restrictions' => 'array',
        'excluded_dates' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function sellerProfile()
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDeliverable($query)
    {
        return $query->where('is_deliverable', true);
    }

    public function scopeByLocation($query, $country, $state = null, $city = null, $township = null)
    {
        return $query->where(function ($q) use ($country, $state, $city, $township) {
            // Match by specific location
            $q->where(function ($sub) use ($country, $state, $city, $township) {
                $sub->where('country', $country);

                if ($state) {
                    $sub->where(function ($s) use ($state) {
                        $s->where('state', $state)
                            ->orWhereNull('state')
                            ->orWhere('area_type', 'country');
                    });
                }

                if ($city) {
                    $sub->where(function ($c) use ($city) {
                        $c->where('city', $city)
                            ->orWhereNull('city')
                            ->orWhereIn('area_type', ['country', 'state']);
                    });
                }

                if ($township) {
                    $sub->where(function ($t) use ($township) {
                        $t->where('township', $township)
                            ->orWhereNull('township')
                            ->orWhereIn('area_type', ['country', 'state', 'city']);
                    });
                }
            });
        });
    }

    // Helper methods
    public function getAreaLabelAttribute()
    {
        switch ($this->area_type) {
            case 'country':
                return "Whole {$this->country}";
            case 'state':
                return "{$this->state} State, {$this->country}";
            case 'city':
                return "{$this->city}, {$this->state}, {$this->country}";
            case 'township':
                return "{$this->township} Township, {$this->city}, {$this->country}";
            case 'specific_address':
                return $this->specific_location;
            default:
                return "{$this->city}, {$this->country}";
        }
    }

    public function getShippingFeeForOrder($orderAmount = 0)
    {
        // Check for free shipping threshold
        if ($this->free_shipping_threshold && $orderAmount >= $this->free_shipping_threshold) {
            return 0;
        }

        return $this->shipping_fee;
    }

    public function getEstimatedDeliveryAttribute()
    {
        if ($this->estimated_delivery_days_min && $this->estimated_delivery_days_max) {
            return "{$this->estimated_delivery_days_min}-{$this->estimated_delivery_days_max} business days";
        } elseif ($this->estimated_delivery_days_min) {
            return "{$this->estimated_delivery_days_min}+ business days";
        }

        return '3-5 business days';
    }
}
