<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_profile_id',
        'enabled',
        'processing_time',
        'custom_processing_time',
        'free_shipping_threshold',
        'free_shipping_enabled',
        'shipping_methods',
        'delivery_areas',
        'shipping_rates',
        'international_shipping',
        'international_rates',
        'package_weight_unit',
        'default_package_weight',
        'shipping_policy',
        'return_policy'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'free_shipping_enabled' => 'boolean',
        'international_shipping' => 'boolean',
        'free_shipping_threshold' => 'decimal:2',
        'default_package_weight' => 'decimal:2',
        'shipping_methods' => 'array',
        'delivery_areas' => 'array',
        'shipping_rates' => 'array',
        'international_rates' => 'array'
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * Get default shipping settings
     */
    public static function getDefaultSettings(): array
    {
        return [
            'enabled' => true,
            'processing_time' => '3_5_days',
            'custom_processing_time' => null,
            'free_shipping_threshold' => null,
            'free_shipping_enabled' => false,
            'shipping_methods' => ['standard'],
            'delivery_areas' => [],
            'shipping_rates' => [
                'standard' => [
                    'type' => 'flat_rate',
                    'amount' => 3000, // MMK
                    'per_additional_item' => 1000
                ]
            ],
            'international_shipping' => false,
            'international_rates' => null,
            'package_weight_unit' => 'kg',
            'default_package_weight' => 1.0,
            'shipping_policy' => 'Orders are processed within 3-5 business days. Delivery times may vary based on location.',
            'return_policy' => 'Returns accepted within 7 days of delivery. Items must be in original condition.'
        ];
    }
}
