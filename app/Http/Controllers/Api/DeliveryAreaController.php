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
                'message' => 'Delivery area created successfully',
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
                    'message' => 'Unauthorized to update this delivery area'
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
                'message' => 'Delivery area updated successfully',
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
                    'message' => 'Unauthorized to delete this delivery area'
                ], 403);
            }

            $deliveryArea->delete();

            Log::info('Delivery area deleted', [
                'user_id' => $user->id,
                'delivery_area_id' => $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery area deleted successfully'
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
                        'message' => 'Delivery not available to this location'
                    ]
                ]);
            }

            // Check weight restrictions
            if ($validated['weight_kg'] && $matchingArea->has_weight_limit &&
                $validated['weight_kg'] > $matchingArea->max_weight_kg) {
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
}