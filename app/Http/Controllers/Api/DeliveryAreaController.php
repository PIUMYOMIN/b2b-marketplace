<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeliveryAreaController extends Controller
{
    /**
     * Get seller's delivery areas
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $areas = $sellerProfile->deliveryAreas()
                ->orderBy('sort_order')
                ->orderBy('area_type')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $areas,
                'count' => $areas->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch delivery areas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery areas'
            ], 500);
        }
    }

    /**
     * Create new delivery area
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'area_type' => 'required|in:country,state,city,township,specific_address',
                'country' => 'required|string|max:100',
                'state' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'township' => 'nullable|string|max:100',
                'specific_location' => 'nullable|string|max:500',
                'postal_code' => 'nullable|string|max:20',
                'is_deliverable' => 'boolean',
                'shipping_fee' => 'required|numeric|min:0',
                'free_shipping_threshold' => 'nullable|numeric|min:0',
                'estimated_delivery_days_min' => 'nullable|integer|min:0',
                'estimated_delivery_days_max' => 'nullable|integer|min:0|gte:estimated_delivery_days_min',
                'standard_shipping_available' => 'boolean',
                'express_shipping_available' => 'boolean',
                'pickup_available' => 'boolean',
                'pickup_location' => 'nullable|string|max:500',
                'has_weight_limit' => 'boolean',
                'max_weight_kg' => 'nullable|numeric|min:0',
                'has_size_limit' => 'boolean',
                'size_restrictions' => 'nullable|array',
                'product_category_restrictions' => 'nullable|array',
                'excluded_dates' => 'nullable|array',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $validated['seller_profile_id'] = $sellerProfile->id;
            $validated['user_id'] = $user->id;

            // Validate area type specific requirements
            if ($validated['area_type'] === 'state' && empty($validated['state'])) {
                return response()->json([
                    'success' => false,
                    'errors' => ['state' => ['State is required for state-level delivery area']]
                ], 422);
            }

            if ($validated['area_type'] === 'city' && empty($validated['city'])) {
                return response()->json([
                    'success' => false,
                    'errors' => ['city' => ['City is required for city-level delivery area']]
                ], 422);
            }

            if ($validated['area_type'] === 'specific_address' && empty($validated['specific_location'])) {
                return response()->json([
                    'success' => false,
                    'errors' => ['specific_location' => ['Specific location is required for specific address delivery area']]
                ], 422);
            }

            // Check for overlapping areas
            $overlap = $this->checkOverlappingArea($sellerProfile->id, $validated);
            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery area overlaps with existing area: ' . $overlap->area_label
                ], 422);
            }

            $deliveryArea = DeliveryArea::create($validated);

            Log::info('Delivery area created', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'delivery_area_id' => $deliveryArea->id,
                'area_type' => $deliveryArea->area_type
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.area_created'),
                'data' => $deliveryArea
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create delivery area: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create delivery area: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update delivery area
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $deliveryArea = DeliveryArea::findOrFail($id);

            // Check ownership
            if ($deliveryArea->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.delivery.unauthorized_update')
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'area_type' => 'sometimes|in:country,state,city,township,specific_address',
                'country' => 'sometimes|string|max:100',
                'state' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'township' => 'nullable|string|max:100',
                'specific_location' => 'nullable|string|max:500',
                'postal_code' => 'nullable|string|max:20',
                'is_deliverable' => 'sometimes|boolean',
                'shipping_fee' => 'sometimes|numeric|min:0',
                'free_shipping_threshold' => 'nullable|numeric|min:0',
                'estimated_delivery_days_min' => 'nullable|integer|min:0',
                'estimated_delivery_days_max' => 'nullable|integer|min:0|gte:estimated_delivery_days_min',
                'standard_shipping_available' => 'sometimes|boolean',
                'express_shipping_available' => 'sometimes|boolean',
                'pickup_available' => 'sometimes|boolean',
                'pickup_location' => 'nullable|string|max:500',
                'has_weight_limit' => 'sometimes|boolean',
                'max_weight_kg' => 'nullable|numeric|min:0',
                'has_size_limit' => 'sometimes|boolean',
                'size_restrictions' => 'nullable|array',
                'product_category_restrictions' => 'nullable|array',
                'excluded_dates' => 'nullable|array',
                'is_active' => 'sometimes|boolean',
                'sort_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Check for overlapping areas (excluding current one)
            $overlap = $this->checkOverlappingArea($deliveryArea->seller_profile_id, $validated, $id);
            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery area overlaps with existing area: ' . $overlap->area_label
                ], 422);
            }

            $deliveryArea->update($validated);

            Log::info('Delivery area updated', [
                'user_id' => $user->id,
                'delivery_area_id' => $deliveryArea->id,
                'area_type' => $deliveryArea->area_type
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.area_updated'),
                'data' => $deliveryArea
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update delivery area: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery area: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete delivery area
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $deliveryArea = DeliveryArea::findOrFail($id);

            // Check ownership
            if ($deliveryArea->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.delivery.unauthorized_delete')
                ], 403);
            }

            $deliveryArea->delete();

            Log::info('Delivery area deleted', [
                'user_id' => $user->id,
                'delivery_area_id' => $id
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.area_deleted')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete delivery area: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete delivery area'
            ], 500);
        }
    }

    /**
     * Check shipping fee for location
     */
    public function checkShippingFee(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'country' => 'required|string|max:100',
                'state' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'township' => 'nullable|string|max:100',
                'order_amount' => 'nullable|numeric|min:0',
                'weight_kg' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Find matching delivery area
            $matchingArea = $sellerProfile->activeDeliveryAreas()
                ->where('country', $validated['country'])
                ->where(function ($query) use ($validated) {
                    $query->where('state', $validated['state'])
                        ->orWhereNull('state')
                        ->orWhere('area_type', 'country');
                })
                ->where(function ($query) use ($validated) {
                    if ($validated['city']) {
                        $query->where('city', $validated['city'])
                            ->orWhereNull('city')
                            ->orWhereIn('area_type', ['country', 'state']);
                    } else {
                        $query->whereNull('city');
                    }
                })
                ->where(function ($query) use ($validated) {
                    if ($validated['township']) {
                        $query->where('township', $validated['township'])
                            ->orWhereNull('township')
                            ->orWhereIn('area_type', ['country', 'state', 'city']);
                    } else {
                        $query->whereNull('township');
                    }
                })
                ->orderBy('area_type', 'desc') // Most specific first
                ->first();

            if (!$matchingArea) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_deliverable' => false,
                        'message' => __('messages.delivery.not_available')
                    ]
                ]);
            }

            // Check weight restrictions
            if (
                $validated['weight_kg'] && $matchingArea->has_weight_limit &&
                $validated['weight_kg'] > $matchingArea->max_weight_kg
            ) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_deliverable' => false,
                        'message' => 'Package exceeds weight limit for this area'
                    ]
                ]);
            }

            $shippingFee = $matchingArea->getShippingFeeForOrder($validated['order_amount'] ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_deliverable' => true,
                    'delivery_area' => $matchingArea,
                    'shipping_fee' => $shippingFee,
                    'free_shipping_threshold' => $matchingArea->free_shipping_threshold,
                    'estimated_delivery' => $matchingArea->estimated_delivery,
                    'standard_shipping_available' => $matchingArea->standard_shipping_available,
                    'express_shipping_available' => $matchingArea->express_shipping_available,
                    'pickup_available' => $matchingArea->pickup_available,
                    'pickup_location' => $matchingArea->pickup_location,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check shipping fee: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check shipping fee'
            ], 500);
        }
    }

    /**
     * Sync delivery zones — replace the seller's entire zone configuration in one call.
     */
    public function sync(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found',
                ], 404);
            }

            $request->validate([
                'zones' => 'required|array',
                'zones.*.area_type' => 'required|in:country,state,city,township',
                'zones.*.country' => 'required|string|max:100',
                'zones.*.state' => 'nullable|string|max:100',
                'zones.*.city' => 'nullable|string|max:100',
                'zones.*.township' => 'nullable|string|max:150',
                'zones.*.shipping_fee' => 'required|numeric|min:0',
                'zones.*.free_shipping_threshold' => 'nullable|numeric|min:0',
                'zones.*.estimated_delivery_days_min' => 'nullable|integer|min:1',
                'zones.*.estimated_delivery_days_max' => 'nullable|integer|min:1',
                'zones.*.is_active' => 'nullable|boolean',
            ]);

            \DB::transaction(function () use ($request, $sellerProfile, $user) {
                // Delete all existing zones for this seller
                DeliveryArea::where('seller_profile_id', $sellerProfile->id)->delete();

                // Insert the new set
                $now = now();
                $rows = collect($request->zones)->map(function ($zone, $index) use ($sellerProfile, $user, $now) {
                    return [
                        'seller_profile_id' => $sellerProfile->id,
                        'user_id' => $user->id,
                        'area_type' => $zone['area_type'],
                        'country' => $zone['country'],
                        'state' => $zone['state'] ?? null,
                        'city' => $zone['city'] ?? null,
                        'township' => $zone['township'] ?? null,
                        'shipping_fee' => $zone['shipping_fee'],
                        'free_shipping_threshold' => $zone['free_shipping_threshold'] ?? null,
                        'estimated_delivery_days_min' => $zone['estimated_delivery_days_min'] ?? null,
                        'estimated_delivery_days_max' => $zone['estimated_delivery_days_max'] ?? null,
                        'is_deliverable' => true,
                        'is_active' => $zone['is_active'] ?? true,
                        'standard_shipping_available' => true,
                        'sort_order' => $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray();

                if (!empty($rows)) {
                    // Chunk inserts to avoid hitting parameter limits on large zone sets
                    foreach (array_chunk($rows, 50) as $chunk) {
                        DeliveryArea::insert($chunk);
                    }
                }
            });

            $saved = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                ->orderBy('sort_order')
                ->get();

            Log::info('Delivery zones synced', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'zone_count' => $saved->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.zones_saved'),
                'data' => $saved,
                'count' => $saved->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync delivery zones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save delivery zones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for overlapping delivery areas
     */
    private function checkOverlappingArea($sellerProfileId, $newAreaData, $excludeId = null)
    {
        $query = DeliveryArea::where('seller_profile_id', $sellerProfileId)
            ->where('country', $newAreaData['country']);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Check different area types for overlap
        switch ($newAreaData['area_type']) {
            case 'country':
                // Country covers everything
                return $query->first();

            case 'state':
                if (isset($newAreaData['state'])) {
                    // State covers: same state, or any area within that state
                    return $query->where(function ($q) use ($newAreaData) {
                        $q->where('area_type', 'country')
                            ->orWhere(function ($sub) use ($newAreaData) {
                                $sub->where('area_type', 'state')
                                    ->where('state', $newAreaData['state']);
                            });
                    })->first();
                }
                break;

            case 'city':
                if (isset($newAreaData['state']) && isset($newAreaData['city'])) {
                    // City covers: country, same state, or same city
                    return $query->where(function ($q) use ($newAreaData) {
                        $q->where('area_type', 'country')
                            ->orWhere(function ($sub) use ($newAreaData) {
                                $sub->where('area_type', 'state')
                                    ->where('state', $newAreaData['state']);
                            })
                            ->orWhere(function ($sub) use ($newAreaData) {
                                $sub->where('area_type', 'city')
                                    ->where('state', $newAreaData['state'])
                                    ->where('city', $newAreaData['city']);
                            });
                    })->first();
                }
                break;

            case 'township':
                if (isset($newAreaData['state']) && isset($newAreaData['city']) && isset($newAreaData['township'])) {
                    // Township covers: country, same state, same city, or same township
                    return $query->where(function ($q) use ($newAreaData) {
                        $q->where('area_type', 'country')
                            ->orWhere(function ($sub) use ($newAreaData) {
                                $sub->where('area_type', 'state')
                                    ->where('state', $newAreaData['state']);
                            })
                            ->orWhere(function ($sub) use ($newAreaData) {
                                $sub->where('area_type', 'city')
                                    ->where('state', $newAreaData['state'])
                                    ->where('city', $newAreaData['city']);
                            })
                            ->orWhere(function ($sub) use ($newAreaData) {
                                $sub->where('area_type', 'township')
                                    ->where('state', $newAreaData['state'])
                                    ->where('city', $newAreaData['city'])
                                    ->where('township', $newAreaData['township']);
                            });
                    })->first();
                }
                break;
        }

        return null;
    }

    /**
     * Get available countries/states/cities for dropdowns
     */
    public function getLocationOptions(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get unique countries
            $countries = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                ->select('country')
                ->distinct()
                ->pluck('country');

            // Get states for each country
            $statesByCountry = [];
            foreach ($countries as $country) {
                $states = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                    ->where('country', $country)
                    ->whereNotNull('state')
                    ->select('state')
                    ->distinct()
                    ->pluck('state');

                $statesByCountry[$country] = $states;
            }

            // Get cities for each state
            $citiesByState = [];
            foreach ($statesByCountry as $country => $states) {
                foreach ($states as $state) {
                    $cities = DeliveryArea::where('seller_profile_id', $sellerProfile->id)
                        ->where('country', $country)
                        ->where('state', $state)
                        ->whereNotNull('city')
                        ->select('city')
                        ->distinct()
                        ->pluck('city');

                    $citiesByState[$country . '.' . $state] = $cities;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'countries' => $countries,
                    'states_by_country' => $statesByCountry,
                    'cities_by_state' => $citiesByState
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get location options: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get location options'
            ], 500);
        }
    }

    /**
     * GET /checkout-locations  (public, no auth)
     *
     * Returns the union of all states & cities configured by active verified sellers.
     * Logic:
     *   - If ANY active seller has a country-level zone  → all Myanmar states (nationwide)
     *   - If sellers have only state/city zones          → return those states + cities
     *   - If no zones configured at all                  → fall back to full Myanmar list
     *
     * The frontend uses this to populate State / City dropdowns in Checkout.
     */
    /**
     * GET /checkout-locations  (public, unauthenticated)
     *
     * Returns states/cities the platform serves, derived from active seller delivery zones.
     * Always returns data — falls back to full Myanmar list so checkout never breaks.
     */
    public function getCheckoutLocations(Request $request): \Illuminate\Http\JsonResponse
    {
        $allStates = [
            ['state' => 'Yangon Region',             'cities' => ['Yangon', 'Thanlyin', 'Hlegu', 'Pathein', 'Tharkayta', 'Dagon Seikkan']],
            ['state' => 'Mandalay Region',           'cities' => ['Mandalay', 'Pyin Oo Lwin', 'Meikhtila', 'Kyaukse', 'Nyaung-U', 'Sagaing']],
            ['state' => 'Naypyidaw Union Territory', 'cities' => ['Naypyidaw', 'Pyinmana', 'Lewe', 'Tatkon']],
            ['state' => 'Sagaing Region',            'cities' => ['Sagaing', 'Monywa', 'Shwebo', 'Katha', 'Kalay']],
            ['state' => 'Bago Region',               'cities' => ['Bago', 'Toungoo', 'Pyay', 'Taungoo', 'Thayarwady']],
            ['state' => 'Magway Region',             'cities' => ['Magway', 'Pakokku', 'Yenangyaung', 'Chauk', 'Minbu']],
            ['state' => 'Ayeyarwady Region',         'cities' => ['Pathein', 'Hinthada', 'Myaungmya', 'Maubin', 'Pyapon']],
            ['state' => 'Tanintharyi Region',        'cities' => ['Dawei', 'Myeik', 'Kawthaung', 'Bokpyin']],
            ['state' => 'Mon State',                 'cities' => ['Mawlamyine', 'Thaton', 'Ye', 'Kyaikto']],
            ['state' => 'Karen State',               'cities' => ['Hpa-an', 'Myawaddy', 'Kawkareik', 'Hlaingbwe']],
            ['state' => 'Karenni State',             'cities' => ['Loikaw', 'Demoso', 'Pruso']],
            ['state' => 'Chin State',                'cities' => ['Hakha', 'Falam', 'Mindat', 'Tedim']],
            ['state' => 'Kachin State',              'cities' => ['Myitkyina', 'Bhamo', 'Putao', 'Mogaung']],
            ['state' => 'Shan State',                'cities' => ['Taunggyi', 'Lashio', 'Kengtung', 'Loilem', 'Hsipaw']],
            ['state' => 'Rakhine State',             'cities' => ['Sittwe', 'Kyaukpyu', 'Thandwe', 'Maungdaw']],
        ];

        // Helper: index $allStates by state name for O(1) lookup
        $statesIndex = collect($allStates)->keyBy('state');

        try {
            // Get IDs of verified, active seller profiles
            $profileIds = SellerProfile::where('verification_status', 'verified')
                ->whereIn('status', ['approved', 'active'])
                ->pluck('id');

            if ($profileIds->isEmpty()) {
                // No verified sellers — return full list so checkout still works
                return response()->json(['success' => true, 'data' => [
                    'nationwide' => true,
                    'states'     => $allStates,
                    'source'     => 'fallback_no_verified_sellers',
                ]]);
            }

            // If any verified seller has a country-level zone → nationwide delivery
            $hasNationwide = DeliveryArea::whereIn('seller_profile_id', $profileIds)
                ->where('area_type', 'country')
                ->where('is_active', true)
                ->where('is_deliverable', true)
                ->exists();

            if ($hasNationwide) {
                return response()->json(['success' => true, 'data' => [
                    'nationwide' => true,
                    'states'     => $allStates,
                    'source'     => 'nationwide_seller',
                ]]);
            }

            // Pull all state/city/township zones from verified sellers
            $areas = DeliveryArea::whereIn('seller_profile_id', $profileIds)
                ->where('is_active', true)
                ->where('is_deliverable', true)
                ->whereIn('area_type', ['state', 'city', 'township'])
                ->whereNotNull('state')
                ->get(['state', 'city', 'area_type']);   // no ->distinct() to avoid SoftDeletes conflict

            if ($areas->isEmpty()) {
                return response()->json(['success' => true, 'data' => [
                    'nationwide' => true,
                    'states'     => $allStates,
                    'source'     => 'fallback_no_zones_configured',
                ]]);
            }

            // Build state → cities map in PHP (avoids DB-level distinct issues)
            $stateMap = [];
            foreach ($areas as $area) {
                $stateName = $area->state;
                if (!isset($stateMap[$stateName])) {
                    $stateMap[$stateName] = [];
                }
                if ($area->area_type === 'state') {
                    // State-level zone → expand to all known cities for that state
                    // Use ->get() with a default to avoid PHP 8 null-access TypeError
                    $knownCities = $statesIndex->get($stateName, ['state' => $stateName, 'cities' => []])['cities'];
                    $stateMap[$stateName] = array_merge($stateMap[$stateName], $knownCities);
                } elseif (!empty($area->city)) {
                    $stateMap[$stateName][] = $area->city;
                }
            }

            // Deduplicate and format
            $result = [];
            foreach ($stateMap as $stateName => $cities) {
                $result[] = [
                    'state'  => $stateName,
                    'cities' => array_values(array_unique($cities)),
                ];
            }

            // Sort alphabetically by state name
            usort($result, fn($a, $b) => strcmp($a['state'], $b['state']));

            return response()->json(['success' => true, 'data' => [
                'nationwide' => false,
                'states'     => $result,
                'source'     => 'seller_zones',
            ]]);

        } catch (\Throwable $e) {
            // Never return a 500 — always fall back to full Myanmar list
            Log::error('getCheckoutLocations failed: ' . $e->getMessage());
            return response()->json(['success' => true, 'data' => [
                'nationwide' => true,
                'states'     => $allStates,
                'source'     => 'fallback_exception',
            ]]);
        }
    }

}