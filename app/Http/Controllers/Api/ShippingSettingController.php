<?php

namespace App\Http\Controllers\Api;

use App\Models\SellerProfile;
use App\Models\ShippingSetting;
use App\Models\DeliveryArea;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ShippingSettingController extends Controller
{
    /**
     * Get shipping settings for authenticated seller
     */
    public function getShippingSettings(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.seller.not_a_seller')
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.seller.profile_not_found')
                ], 404);
            }

            // Get or create shipping settings
            $shippingSetting = ShippingSetting::firstOrCreate(
                ['seller_profile_id' => $sellerProfile->id],
                ShippingSetting::getDefaultSettings()
            );

            // Get delivery areas
            $deliveryAreas = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                ->orderBy('sort_order')
                ->get();

            // Calculate coverage summary
            $coverageSummary = $this->calculateCoverageSummary($deliveryAreas);

            return response()->json([
                'success' => true,
                'data' => [
                    'shipping_settings' => $shippingSetting,
                    'delivery_areas' => $deliveryAreas,
                    'coverage_summary' => $coverageSummary,
                    'seller_shipping_enabled' => $sellerProfile->shipping_enabled
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get shipping settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping settings'
            ], 500);
        }
    }

    /**
     * Update shipping settings for authenticated seller
     */
    public function updateShippingSettings(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.seller.not_a_seller')
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.seller.profile_not_found')
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                // Basic Settings
                'enabled' => 'sometimes|boolean',
                // processing_time: accept both enum keys AND human-readable labels
                // (normalised to DB enum before saving)
                'processing_time' => 'sometimes|nullable|string|max:50',
                'custom_processing_time' => 'nullable|string|max:255',
                // Shipping fee fallback + buyer-facing notes
                'default_shipping_fee' => 'sometimes|numeric|min:0',
                'shipping_notes' => 'nullable|string|max:1000',

                // Free Shipping
                'free_shipping_enabled' => 'sometimes|boolean',
                'free_shipping_threshold' => 'nullable|numeric|min:0',

                // Shipping Methods
                'shipping_methods' => 'sometimes|array',
                'shipping_methods.*' => 'in:standard,express,next_day,pickup',

                // Shipping Rates
                'shipping_rates' => 'sometimes|array',
                'shipping_rates.standard.type' => 'required_with:shipping_rates.standard|in:flat_rate,weight_based,price_based',
                'shipping_rates.standard.amount' => 'required_with:shipping_rates.standard|numeric|min:0',
                'shipping_rates.express.type' => 'nullable|in:flat_rate,weight_based,price_based',
                'shipping_rates.express.amount' => 'nullable|numeric|min:0',

                // International Shipping (request may send _enabled; stored as international_shipping)
                'international_shipping' => 'sometimes|boolean',
                'international_shipping_enabled' => 'sometimes|boolean',
                'international_rates' => 'nullable|array',

                // Package Settings
                'package_weight_unit' => 'sometimes|in:kg,g,lb,oz',
                'default_package_weight' => 'sometimes|numeric|min:0.01|max:50',

                // Policies
                'shipping_policy' => 'nullable|string|max:5000',
                'return_policy' => 'nullable|string|max:5000',

                // Delivery Areas
                'delivery_areas' => 'sometimes|array',
                'delivery_areas.*.id' => 'nullable|exists:seller_delivery_areas,id',
                'delivery_areas.*.city' => 'required|string|max:100',
                'delivery_areas.*.state' => 'required|string|max:100',
                'delivery_areas.*.country' => 'required|string|max:100',
                'delivery_areas.*.zip_codes' => 'nullable|string|max:500',
                'delivery_areas.*.delivery_time' => 'required|string|max:50',
                'delivery_areas.*.shipping_method' => 'required|in:standard,express,next_day,pickup',
                'delivery_areas.*.rate' => 'required|numeric|min:0',
                'delivery_areas.*.min_order_amount' => 'nullable|numeric|min:0',
                'delivery_areas.*.is_active' => 'boolean',
                'delivery_areas.*.sort_order' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Get or create shipping settings
            $shippingSetting = ShippingSetting::firstOrCreate(
                ['seller_profile_id' => $sellerProfile->id],
                ShippingSetting::getDefaultSettings()
            );

            $intl = $validated['international_shipping']
                ?? $validated['international_shipping_enabled']
                ?? $shippingSetting->international_shipping;

            // ── Normalise processing_time ─────────────────────────────────────
            // Frontend may send human-readable strings ("1-2 days", "Same day", etc.)
            // Map them to the DB enum values before saving.
            $processingTimeMap = [
                'same day'   => 'same_day',
                'same_day'   => 'same_day',
                '1 day'      => '1_2_days',
                '1-2 days'   => '1_2_days',
                '1_2_days'   => '1_2_days',
                '2-3 days'   => '3_5_days',
                '3-5 days'   => '3_5_days',
                '3_5_days'   => '3_5_days',
                '4-7 days'   => '5_7_days',
                '5-7 days'   => '5_7_days',
                '5_7_days'   => '5_7_days',
                '1 week'     => '5_7_days',
                '1-2 weeks'  => 'custom',
                'custom'     => 'custom',
            ];
            $rawProcessingTime  = strtolower(trim($validated['processing_time'] ?? ''));
            $normProcessingTime = $processingTimeMap[$rawProcessingTime]
                ?? (in_array($rawProcessingTime, ['same_day','1_2_days','3_5_days','5_7_days','custom'], true)
                    ? $rawProcessingTime
                    : $shippingSetting->processing_time);

            // Update shipping settings (only columns on shipping_settings / model $fillable)
            $shippingSetting->update([
                'enabled' => $validated['enabled'] ?? $shippingSetting->enabled,
                'processing_time' => $normProcessingTime,
                'custom_processing_time' => $validated['custom_processing_time'] ?? $shippingSetting->custom_processing_time,
                'free_shipping_enabled' => $validated['free_shipping_enabled'] ?? $shippingSetting->free_shipping_enabled,
                'free_shipping_threshold' => $validated['free_shipping_threshold'] ?? $shippingSetting->free_shipping_threshold,
                'shipping_methods' => $validated['shipping_methods'] ?? $shippingSetting->shipping_methods,
                'shipping_rates' => $validated['shipping_rates'] ?? $shippingSetting->shipping_rates,
                'international_shipping' => (bool) $intl,
                'international_rates' => $validated['international_rates'] ?? $shippingSetting->international_rates,
                'package_weight_unit' => $validated['package_weight_unit'] ?? $shippingSetting->package_weight_unit,
                'default_package_weight' => $validated['default_package_weight'] ?? $shippingSetting->default_package_weight,
                'shipping_policy' => $validated['shipping_policy'] ?? $shippingSetting->shipping_policy,
                'return_policy' => $validated['return_policy'] ?? $shippingSetting->return_policy,
                'default_shipping_fee' => $validated['default_shipping_fee'] ?? $shippingSetting->default_shipping_fee,
                'shipping_notes' => $validated['shipping_notes'] ?? $shippingSetting->shipping_notes,
            ]);

            // Update seller profile shipping_enabled flag
            if (isset($validated['enabled'])) {
                $sellerProfile->update(['shipping_enabled' => $validated['enabled']]);
            }

            // Sync delivery areas if provided
            if (isset($validated['delivery_areas'])) {
                $this->syncDeliveryAreas($sellerProfile->id, (int) $sellerProfile->user_id, $validated['delivery_areas']);
            }

            Log::info('Shipping settings updated', [
                'seller_profile_id' => $sellerProfile->id,
                'updated_fields' => array_keys($validated)
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.settings_updated'),
                'data' => $shippingSetting->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update shipping settings: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update shipping settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync delivery areas for seller
     */
    private function syncDeliveryAreas(int $sellerProfileId, int $userId, array $deliveryAreas): void
    {
        $existingIds = [];

        foreach ($deliveryAreas as $area) {
            $row = $this->mapAreaPayloadToRow($area, $sellerProfileId, $userId);

            if (!empty($area['id'])) {
                $deliveryArea = DeliveryArea::where('id', $area['id'])
                    ->where('seller_profile_id', $sellerProfileId)
                    ->first();

                if ($deliveryArea) {
                    $deliveryArea->update($row);
                    $existingIds[] = (int) $deliveryArea->id;
                }
            } else {
                $deliveryArea = DeliveryArea::create($row);
                $existingIds[] = (int) $deliveryArea->id;
            }
        }

        DeliveryArea::where('seller_profile_id', $sellerProfileId)
            ->whereNotIn('id', $existingIds)
            ->delete();
    }

    /**
     * Calculate coverage summary
     */
    private function calculateCoverageSummary($deliveryAreas)
    {
        $activeAreas = $deliveryAreas->where('is_active', true);

        return [
            'total_areas' => $deliveryAreas->count(),
            'active_areas' => $activeAreas->count(),
            'unique_cities' => $deliveryAreas->pluck('city')->unique()->count(),
            'unique_states' => $deliveryAreas->pluck('state')->unique()->count(),
            'unique_countries' => $deliveryAreas->pluck('country')->unique()->count(),
            'average_rate' => $activeAreas->avg('shipping_fee') ?? 0,
            'min_rate' => $activeAreas->min('shipping_fee') ?? 0,
            'max_rate' => $activeAreas->max('shipping_fee') ?? 0,
        ];
    }

    /**
     * Parse strings like "3-5 days" or "2" into [min, max] day integers.
     *
     * @return array{0: int, 1: int}
     */
    private function parseDeliveryTimeRange(?string $deliveryTime): array
    {
        if (!$deliveryTime) {
            return [2, 5];
        }
        if (preg_match('/(\d+)\s*-\s*(\d+)/', $deliveryTime, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/(\d+)/', $deliveryTime, $m)) {
            $n = (int) $m[1];

            return [$n, $n];
        }

        return [2, 5];
    }

    /**
     * Map simplified API fields to seller_delivery_areas columns.
     */
    private function mapAreaPayloadToRow(array $area, int $sellerProfileId, int $userId): array
    {
        $method = $area['shipping_method'] ?? 'standard';
        [$minDays, $maxDays] = $this->parseDeliveryTimeRange($area['delivery_time'] ?? null);

        $zip = $area['zip_codes'] ?? $area['postal_code'] ?? null;
        if (is_string($zip) && str_contains($zip, ',')) {
            $zip = trim(explode(',', $zip, 2)[0]);
        }
        $zip = $zip !== null && $zip !== '' ? substr($zip, 0, 20) : null;

        $fee = $area['rate'] ?? $area['shipping_fee'] ?? 0;

        return [
            'seller_profile_id' => $sellerProfileId,
            'user_id' => $userId,
            'area_type' => 'city',
            'country' => $area['country'],
            'state' => $area['state'],
            'city' => $area['city'],
            'postal_code' => $zip,
            'is_deliverable' => true,
            'shipping_fee' => $fee,
            'estimated_delivery_days_min' => $minDays,
            'estimated_delivery_days_max' => max($minDays, $maxDays),
            'standard_shipping_available' => in_array($method, ['standard', 'express', 'next_day', 'pickup'], true),
            'express_shipping_available' => in_array($method, ['express', 'next_day'], true),
            'pickup_available' => $method === 'pickup',
            'is_active' => $area['is_active'] ?? true,
            'sort_order' => $area['sort_order'] ?? 0,
        ];
    }

    /**
     * Calculate shipping cost for an order
     */
    public function calculateShipping(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'seller_id' => 'required|exists:seller_profiles,user_id',
                'items' => 'nullable|array',
                'items.*.product_id' => 'required_with:items|exists:products,id',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.weight' => 'nullable|numeric|min:0',
                'items.*.dimensions' => 'nullable|array',
                'items_count' => 'nullable|integer|min:1',
                'delivery_address' => 'nullable|array',
                'delivery_address.city' => 'nullable|string|max:100',
                'delivery_address.state' => 'nullable|string|max:100',
                'delivery_address.country' => 'nullable|string|max:100',
                'delivery_address.zip_code' => 'nullable|string|max:20',
                'delivery_city' => 'nullable|string|max:100',
                'delivery_state' => 'nullable|string|max:100',
                'delivery_country' => 'nullable|string|max:100',
                'delivery_zip_code' => 'nullable|string|max:20',
                'shipping_method' => 'sometimes|in:standard,express,next_day,pickup',
                'total_amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $addr = $validated['delivery_address'] ?? [];
            if (($addr['city'] ?? '') === '' && ($validated['delivery_city'] ?? '') !== '') {
                $addr = [
                    'city' => $validated['delivery_city'],
                    'state' => $validated['delivery_state'] ?? '',
                    'country' => $validated['delivery_country'] ?? '',
                    'zip_code' => $validated['delivery_zip_code'] ?? null,
                ];
            }

            if (($addr['city'] ?? '') === '' || ($addr['state'] ?? '') === '' || ($addr['country'] ?? '') === '') {
                return response()->json([
                    'success' => false,
                    'errors' => ['delivery' => ['City, state, and country are required for shipping calculation.']],
                ], 422);
            }

            $sellerProfile = SellerProfile::where('user_id', $validated['seller_id'])->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller not found'
                ], 404);
            }

            // Check if seller has shipping enabled
            if (!$sellerProfile->shipping_enabled) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'shipping_available' => false,
                        'message' => 'Shipping not available from this seller'
                    ]
                ]);
            }

            $shippingSetting = ShippingSetting::where('seller_profile_id', $sellerProfile->id)->first();

            if (!$shippingSetting || !$shippingSetting->enabled) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'shipping_available' => false,
                        'message' => 'Shipping not available from this seller'
                    ]
                ]);
            }

            // Check if delivery area is covered
            $deliveryArea = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                ->where('city', $addr['city'])
                ->where('state', $addr['state'])
                ->where('country', $addr['country'])
                ->where('is_active', true)
                ->first();

            if (!$deliveryArea) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'shipping_available' => false,
                        'message' => __('messages.delivery.not_available_location')
                    ]
                ]);
            }

            if (!empty($addr['zip_code']) && $deliveryArea->postal_code) {
                $zipCodes = array_map('trim', explode(',', $deliveryArea->postal_code));
                if (!in_array($addr['zip_code'], $zipCodes, true)) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'shipping_available' => false,
                            'message' => __('messages.delivery.not_available_zip')
                        ]
                    ]);
                }
            }

            // Check if free shipping applies
            $isFreeShipping = false;
            if ($shippingSetting->free_shipping_enabled && $shippingSetting->free_shipping_threshold) {
                if ($validated['total_amount'] >= $shippingSetting->free_shipping_threshold) {
                    $isFreeShipping = true;
                }
            }

            // Per-area free shipping threshold (optional)
            if (
                !$isFreeShipping
                && $deliveryArea->free_shipping_threshold
                && $validated['total_amount'] >= $deliveryArea->free_shipping_threshold
            ) {
                $isFreeShipping = true;
            }

            // Calculate shipping cost
            $shippingCost = 0;
            $defaultMethod = $deliveryArea->pickup_available ? 'pickup' : 'standard';
            if ($deliveryArea->express_shipping_available && !$deliveryArea->standard_shipping_available) {
                $defaultMethod = 'express';
            }
            $shippingMethod = $validated['shipping_method'] ?? $defaultMethod;

            if (!$isFreeShipping) {
                $shippingCost = (float) $deliveryArea->shipping_fee;

                // Apply additional calculations based on shipping method
                if (isset($shippingSetting->shipping_rates[$shippingMethod])) {
                    $rateConfig = $shippingSetting->shipping_rates[$shippingMethod];

                    if (($rateConfig['type'] ?? '') === 'weight_based' && !empty($validated['items'])) {
                        $totalWeight = 0;
                        foreach ($validated['items'] as $item) {
                            $weight = $item['weight'] ?? $shippingSetting->default_package_weight;
                            $totalWeight += $weight * $item['quantity'];
                        }

                        $shippingCost += $this->calculateWeightBasedCost($totalWeight, $rateConfig);
                    }
                }
            }

            // Get estimated delivery
            $estimatedDelivery = $this->getEstimatedDelivery($shippingSetting, $deliveryArea, $shippingMethod);

            return response()->json([
                'success' => true,
                'data' => [
                    'shipping_available' => true,
                    'is_free_shipping' => $isFreeShipping,
                    'shipping_cost' => $shippingCost,
                    'shipping_cost_formatted' => 'MMK ' . number_format($shippingCost, 2),
                    'shipping_method' => $shippingMethod,
                    'delivery_area' => [
                        'city' => $deliveryArea->city,
                        'state' => $deliveryArea->state,
                        'country' => $deliveryArea->country,
                        'postal_code' => $deliveryArea->postal_code,
                    ],
                    'estimated_delivery' => $estimatedDelivery,
                    'currency' => 'MMK'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate shipping: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping'
            ], 500);
        }
    }

    /**
     * Calculate weight-based shipping cost
     */
    private function calculateWeightBasedCost($totalWeight, $rateConfig)
    {
        $additionalCost = 0;

        if (isset($rateConfig['per_kg_rate']) && $rateConfig['per_kg_rate'] > 0) {
            $additionalCost = $totalWeight * $rateConfig['per_kg_rate'];
        }

        if (isset($rateConfig['weight_ranges'])) {
            foreach ($rateConfig['weight_ranges'] as $range) {
                if ($totalWeight >= $range['min'] && $totalWeight <= $range['max']) {
                    $additionalCost = $range['cost'];
                    break;
                }
            }
        }

        return $additionalCost;
    }

    /**
     * Get estimated delivery information
     */
    private function getEstimatedDelivery($shippingSetting, $deliveryArea, $shippingMethod)
    {
        // Base processing days
        $processingDays = 3; // Default

        switch ($shippingSetting->processing_time) {
            case 'same_day':
                $processingDays = 0;
                break;
            case '1_2_days':
                $processingDays = 1;
                break;
            case '3_5_days':
                $processingDays = 3;
                break;
            case '5_7_days':
                $processingDays = 5;
                break;
            case 'custom':
                if ($shippingSetting->custom_processing_time) {
                    // Parse custom processing time (e.g., "2-3 days")
                    preg_match('/(\d+)/', $shippingSetting->custom_processing_time, $matches);
                    $processingDays = isset($matches[1]) ? (int) $matches[1] : 3;
                }
                break;
        }

        // Add shipping method time
        $shippingDays = 2; // Default standard shipping

        switch ($shippingMethod) {
            case 'express':
                $shippingDays = 1;
                break;
            case 'next_day':
                $shippingDays = 0; // Next day delivery
                break;
            case 'pickup':
                $shippingDays = 0;
                break;
        }

        $areaDeliveryDays = 2;
        if ($deliveryArea->estimated_delivery_days_max !== null) {
            $areaDeliveryDays = (int) $deliveryArea->estimated_delivery_days_max;
        } elseif ($deliveryArea->estimated_delivery_days_min !== null) {
            $areaDeliveryDays = (int) $deliveryArea->estimated_delivery_days_min;
        }

        // Calculate total days
        $totalDays = $processingDays + max($shippingDays, $areaDeliveryDays);

        // Calculate delivery date (skip weekends)
        $deliveryDate = now();
        $daysAdded = 0;

        while ($daysAdded < $totalDays) {
            $deliveryDate->addDay();
            // Skip weekends (Saturday = 6, Sunday = 0)
            if (!in_array($deliveryDate->dayOfWeek, [0, 6])) {
                $daysAdded++;
            }
        }

        return [
            'date' => $deliveryDate->format('Y-m-d'),
            'days' => $totalDays,
            'business_days' => $daysAdded,
            'formatted' => $deliveryDate->format('F j, Y'),
            'processing_time' => $shippingSetting->processing_time,
            'estimated_delivery_days_min' => $deliveryArea->estimated_delivery_days_min,
            'estimated_delivery_days_max' => $deliveryArea->estimated_delivery_days_max,
            'shipping_method' => $shippingMethod
        ];
    }

    /**
     * Get shipping methods available for a seller
     */
    public function getShippingMethods(Request $request, $sellerId)
    {
        try {
            $sellerProfile = SellerProfile::where('user_id', $sellerId)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller not found'
                ], 404);
            }

            $shippingSetting = ShippingSetting::where('seller_profile_id', $sellerProfile->id)->first();

            if (!$shippingSetting || !$shippingSetting->enabled) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'available' => false,
                        'methods' => []
                    ]
                ]);
            }

            $methods = [];

            foreach ($shippingSetting->shipping_methods ?? [] as $method) {
                $rate = $shippingSetting->shipping_rates[$method] ?? null;

                $methods[] = [
                    'code' => $method,
                    'name' => $this->getShippingMethodName($method),
                    'description' => $this->getShippingMethodDescription($method),
                    'rate' => $rate,
                    'estimated_days' => $this->getEstimatedDaysForMethod($method),
                    'available' => true
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'available' => true,
                    'methods' => $methods,
                    'free_shipping_threshold' => $shippingSetting->free_shipping_threshold,
                    'free_shipping_enabled' => $shippingSetting->free_shipping_enabled
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get shipping methods: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping methods'
            ], 500);
        }
    }

    /**
     * Get shipping method display name
     */
    private function getShippingMethodName($method)
    {
        $names = [
            'standard' => 'Standard Shipping',
            'express' => 'Express Shipping',
            'next_day' => 'Next Day Delivery',
            'pickup' => 'Store Pickup'
        ];

        return $names[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    /**
     * Get shipping method description
     */
    private function getShippingMethodDescription($method)
    {
        $descriptions = [
            'standard' => 'Regular shipping with tracking',
            'express' => 'Faster delivery with priority handling',
            'next_day' => 'Delivery on the next business day',
            'pickup' => 'Pick up from seller location'
        ];

        return $descriptions[$method] ?? '';
    }

    /**
     * Get estimated days for shipping method
     */
    private function getEstimatedDaysForMethod($method)
    {
        $days = [
            'standard' => '3-5 business days',
            'express' => '1-2 business days',
            'next_day' => 'Next business day',
            'pickup' => 'Ready for pickup in 1-2 hours'
        ];

        return $days[$method] ?? '';
    }

    /**
     * Toggle shipping enabled status
     */
    public function toggleShipping(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can toggle shipping'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.seller.profile_not_found')
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Update seller profile
            $sellerProfile->update(['shipping_enabled' => $validated['enabled']]);

            // Update or create shipping settings
            $shippingSetting = ShippingSetting::firstOrCreate(
                ['seller_profile_id' => $sellerProfile->id],
                ShippingSetting::getDefaultSettings()
            );

            $shippingSetting->update(['enabled' => $validated['enabled']]);

            Log::info('Shipping toggled', [
                'seller_profile_id' => $sellerProfile->id,
                'enabled' => $validated['enabled']
            ]);

            return response()->json([
                'success' => true,
                'message' => $validated['enabled'] ? 'Shipping enabled' : 'Shipping disabled',
                'data' => [
                    'shipping_enabled' => $validated['enabled'],
                    'seller_profile' => $sellerProfile,
                    'shipping_settings' => $shippingSetting
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle shipping: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle shipping'
            ], 500);
        }
    }

    /**
     * Get shipping coverage areas
     */
    public function getCoverageAreas(Request $request, $sellerId)
    {
        try {
            $sellerProfile = SellerProfile::where('user_id', $sellerId)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller not found'
                ], 404);
            }

            $deliveryAreas = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                ->where('is_active', true)
                ->orderBy('country')
                ->orderBy('state')
                ->orderBy('city')
                ->get()
                ->groupBy('country');

            $coverage = [];
            foreach ($deliveryAreas as $country => $areas) {
                $coverage[$country] = $areas->groupBy('state')->map(function ($stateAreas) {
                    return $stateAreas->map(function ($area) {
                        return [
                            'city' => $area->city,
                            'postal_code' => $area->postal_code,
                            'estimated_delivery_days_min' => $area->estimated_delivery_days_min,
                            'estimated_delivery_days_max' => $area->estimated_delivery_days_max,
                            'shipping_fee' => $area->shipping_fee,
                            'standard_shipping_available' => $area->standard_shipping_available,
                            'express_shipping_available' => $area->express_shipping_available,
                            'pickup_available' => $area->pickup_available,
                        ];
                    });
                });
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'seller_id' => $sellerId,
                    'store_name' => $sellerProfile->store_name,
                    'shipping_enabled' => $sellerProfile->shipping_enabled,
                    'coverage_areas' => $coverage,
                    'total_areas' => DeliveryArea::where('seller_profile_id', $sellerProfile->id)->where('is_active', true)->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get coverage areas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get coverage areas'
            ], 500);
        }
    }

    /**
     * Check shipping availability for specific address
     */
    public function checkAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'seller_id' => 'required|exists:seller_profiles,user_id',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'country' => 'required|string|max:100',
                'zip_code' => 'nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $sellerProfile = SellerProfile::where('user_id', $validated['seller_id'])->first();

            if (!$sellerProfile || !$sellerProfile->shipping_enabled) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'available' => false,
                        'reason' => 'Seller does not offer shipping'
                    ]
                ]);
            }

            $deliveryArea = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                ->where('city', $validated['city'])
                ->where('state', $validated['state'])
                ->where('country', $validated['country'])
                ->where('is_active', true)
                ->first();

            if (!$deliveryArea) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'available' => false,
                        'reason' => 'Location not in delivery area'
                    ]
                ]);
            }

            // Check zip code if specified
            if ($deliveryArea->postal_code && $validated['zip_code']) {
                $zipCodes = array_map('trim', explode(',', $deliveryArea->postal_code));
                if (!in_array($validated['zip_code'], $zipCodes, true)) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'available' => false,
                            'reason' => 'Zip code not in delivery area'
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'available' => true,
                    'delivery_area' => [
                        'city' => $deliveryArea->city,
                        'state' => $deliveryArea->state,
                        'country' => $deliveryArea->country,
                        'postal_code' => $deliveryArea->postal_code,
                        'shipping_fee' => $deliveryArea->shipping_fee,
                        'estimated_delivery_days_min' => $deliveryArea->estimated_delivery_days_min,
                        'estimated_delivery_days_max' => $deliveryArea->estimated_delivery_days_max,
                    ],
                    'estimated_delivery' => $this->getEstimatedDelivery(
                        ShippingSetting::firstWhere('seller_profile_id', $sellerProfile->id)
                            ?? new ShippingSetting(ShippingSetting::getDefaultSettings()),
                        $deliveryArea,
                        $deliveryArea->pickup_available ? 'pickup' : 'standard'
                    )
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check shipping availability: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check shipping availability'
            ], 500);
        }
    }
}