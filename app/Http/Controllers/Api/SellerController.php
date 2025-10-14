<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\SellerReview;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SellerController extends Controller
{
    /**
     * Get all sellers (public endpoint)
     */
    public function index(Request $request)
    {
        // ✅ Handle "top sellers" case
        if ($request->boolean('top')) {
            $topSellers = SellerProfile::with(['user'])
                ->withAvg('reviews', 'rating')
                ->withCount(['reviews', 'products']) // products_count
                ->withCount(['orders as customers_count' => function($q) {
                    $q->distinct('user_id'); // unique customers
                }])
                ->whereIn('status', ['approved','active'])
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('reviews_count')
                ->take(6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $topSellers
            ]);
        }

        // Validate filters and pagination
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
            'business_type' => 'sometimes|string|in:individual,company,retail,wholesale,manufacturer',
            'city' => 'sometimes|string|max:100',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'sort' => 'sometimes|in:newest,rating,name'
        ]);

        // Validation failed
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        
        // Base query
        $query = SellerProfile::with(['user', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereIn('status', ['approved','active']);

        // Apply filters
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('store_name', 'like', '%'.$request->search.'%')
                  ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->has('business_type')) {
            $query->where('business_type', $request->business_type);
        }

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        if ($request->has('min_rating')) {
            $query->having('reviews_avg_rating', '>=', $request->min_rating);
        }

        // Apply sorting
        switch ($request->input('sort', 'newest')) {
            case 'rating':
                $query->orderBy('reviews_avg_rating', 'desc');
                break;
            case 'name':
                $query->orderBy('store_name', 'asc');
                break;
            default: // newest
                $query->latest();
        }

        $sellers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sellers,
            'meta' => [
                'current_page' => $sellers->currentPage(),
                'per_page' => $sellers->perPage(),
                'total' => $sellers->total(),
                'last_page' => $sellers->lastPage(),
            ]
        ]);
    }

    /**
     * Create a new seller profile
     */
    public function store(Request $request)
    {
        // Check if user already has a seller profile
        $sellerProfile = SellerProfile::where('user_id', $request->user()->id)->first();
        
        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found'
            ], 404);
        }

        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'business_type' => 'required|in:retail,wholesale,service,individual,company',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'store_logo' => 'nullable|string', // URL from uploaded image
            'store_banner' => 'nullable|string',
        ]);

        // Generate store slug if store name is provided and different
        $storeSlug = $sellerProfile->store_slug;
        if (!empty($validated['store_name']) && $validated['store_name'] !== $sellerProfile->store_name) {
            $storeSlug = SellerProfile::generateStoreSlug($validated['store_name']);
        }

        // Update the seller profile
        $sellerProfile->update(array_merge($validated, [
            'store_slug' => $storeSlug,
            'status' => SellerProfile::STATUS_PENDING // Change from pending_setup to pending for approval
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Seller profile updated successfully',
            'data' => $sellerProfile->fresh()
        ]);
    }

    public function updateBusinessDetails(Request $request)
    {
        $sellerProfile = SellerProfile::where('user_id', $request->user()->id)->first();
        
        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found'
            ], 404);
        }

        $validated = $request->validate([
            'business_registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'account_number' => 'nullable|string|max:255|unique:seller_profiles,account_number,' . $sellerProfile->id,
            'social_facebook' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
        ]);

        $sellerProfile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Business details updated successfully',
            'data' => $sellerProfile->fresh()
        ]);
    }

    public function updateAddress(Request $request)
    {
        $sellerProfile = SellerProfile::where('user_id', $request->user()->id)->first();
        
        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found'
            ], 404);
        }

        $validated = $request->validate([
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
        ]);

        $sellerProfile->update($validated);

        // If all required fields are filled, mark as pending for admin approval
        if ($sellerProfile->isOnboardingComplete()) {
            $sellerProfile->update(['status' => SellerProfile::STATUS_PENDING]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address information updated successfully',
            'data' => $sellerProfile->fresh()
        ]);
    }

    public function myStore(Request $request)
    {
        $sellerProfile = SellerProfile::where('user_id', $request->user()->id)->first();

        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sellerProfile
        ]);
    }

    // In SellerController - update the getOnboardingStatus method
public function getOnboardingStatus(Request $request)
{
    try {
        $user = $request->user();
        
        \Log::info('Checking onboarding status for user: ' . $user->id);
        \Log::info('User roles: ' . json_encode($user->roles ?? $user->type));

        $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

        if (!$sellerProfile) {
            \Log::info('No seller profile found for user: ' . $user->id);
            return response()->json([
                'success' => true,
                'data' => [
                    'has_profile' => false,
                    'onboarding_complete' => false,
                    'current_step' => 'store-basic',
                    'profile_status' => 'not_created',
                    'user_has_seller_role' => $user->hasRole('seller') || $user->type === 'seller'
                ]
            ]);
        }

        $onboardingComplete = $sellerProfile->isOnboardingComplete();
        $currentStep = $sellerProfile->getOnboardingStep();

        \Log::info('Seller profile found - ID: ' . $sellerProfile->id . ', Status: ' . $sellerProfile->status);

        return response()->json([
            'success' => true,
            'data' => [
                'has_profile' => true,
                'onboarding_complete' => $onboardingComplete,
                'current_step' => $currentStep,
                'profile_status' => $sellerProfile->status,
                'profile' => $sellerProfile,
                'user_has_seller_role' => true
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error in getOnboardingStatus: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to get onboarding status'
        ], 500);
    }
}


    /**
     * Get seller details (public endpoint)
     */
//     public function showPublic($id)
// {
//     try {
//         \Log::info('showPublic called with ID: ' . $id);
        
//         $seller = SellerProfile::where('id', $id)
//             ->orWhere('store_slug', $id)
//             ->orWhere('store_id', $id)
//             ->with(['user', 'reviews.user'])
//             ->withAvg('reviews', 'rating')
//             ->withCount('reviews')
//             ->firstOrFail();

//         \Log::info('Seller found: ' . $seller->id . ', Status: ' . $seller->status);

//         // Allow both 'approved' and 'active' statuses
//         if (!in_array($seller->status, ['approved', 'active'])) {
//             \Log::info('Seller status not allowed: ' . $seller->status);
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Seller profile not found'
//             ], 404);
//         }

//         \Log::info('Seller status is valid, proceeding...');

//         // Get seller's products (only active ones) - FIXED: Use seller's user_id
//         $products = Product::where('seller_id', $seller->user_id)
//             ->where('is_active', true)
//             ->with(['category'])
//             ->withAvg('reviews', 'rating')
//             ->withCount('reviews')
//             ->paginate(12);

//         \Log::info('Products found: ' . $products->count());

//         // Get seller stats - FIXED: Use seller's user_id
//         $stats = [
//             'total_products' => Product::where('seller_id', $seller->user_id)->count(),
//             'active_products' => Product::where('seller_id', $seller->user_id)
//                 ->where('is_active', true)->count(),
//             'total_orders' => $seller->user->orders()->count(),
//             'member_since' => $seller->created_at->format('M Y')
//         ];

//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'seller' => $seller,
//                 'products' => $products,
//                 'stats' => $stats
//             ]
//         ]);

//     } catch (\Exception $e) {
//         \Log::error('Error in showPublic: ' . $e->getMessage());
//         \Log::error('Stack trace: ' . $e->getTraceAsString());
        
//         return response()->json([
//             'success' => false,
//             'message' => 'Seller not found: ' . $e->getMessage()
//         ], 404);
//     }
// }

public function show($idOrSlug)
    {
        try {
            $seller = SellerProfile::where('id', $idOrSlug)
                ->orWhere('store_slug', $idOrSlug)
                ->orWhere('store_id', $idOrSlug)
                ->with(['user', 'reviews.user'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->firstOrFail();

            if ($seller->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get seller's products (only active ones)
            $products = Product::where('seller_id', $seller->user_id)
                ->where('is_active', true)
                ->with(['category'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->paginate(12);

            // Get seller stats
            $stats = [
                'total_products' => Product::where('seller_id', $seller->user_id)->count(),
                'active_products' => Product::where('seller_id', $seller->user_id)
                    ->where('is_active', true)->count(),
                'total_orders' => $seller->user->orders()->count(),
                'member_since' => $seller->created_at->format('M Y')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'seller' => $seller,
                    'products' => $products,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Seller not found'
            ], 404);
        }
    }

    /**
     * Update seller profile
     */
    public function update(Request $request, $id)
{
    try {
        $seller = SellerProfile::findOrFail($id);
        $user = auth()->user();

        // Authorization check
        if ($user->id !== $seller->user_id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this seller profile'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'store_name' => 'sometimes|string|max:255|unique:seller_profiles,store_name,'.$id,
            'business_type' => 'sometimes|in:individual,company,retail,wholesale,manufacturer',
            'business_registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'description' => 'sometimes|string|min:100|max:2000',
            'contact_email' => 'sometimes|email|max:255',
            'contact_phone' => 'sometimes|string|max:20',
            'website' => 'nullable|url|max:255',
            'social_facebook' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',
            'social_youtube' => 'nullable|url|max:255',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'country' => 'sometimes|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'store_logo' => 'nullable|string|max:500', // Change from image validation if you're handling uploads separately
            'store_banner' => 'nullable|string|max:500',
            'certificate' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:255',
            'year_established' => 'nullable|integer|min:1900|max:'.date('Y'),
            'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
            'production_capacity' => 'nullable|string|max:255',
            'quality_certifications' => 'nullable|array',
            'quality_certifications.*' => 'string|max:255',
            'status' => 'sometimes|in:pending,approved,rejected,suspended,closed',
            'reason' => 'sometimes|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Only admin can change status - FIXED: This was in the wrong place
        if (!$user->hasRole('admin') && isset($validated['status'])) {
            unset($validated['status']);
        }

        // Admin status update logic - FIXED: Move this inside the method
        if ($user->hasRole('admin') && isset($validated['status'])) {
            $seller->status = $validated['status'];

            // Optional: Store admin notes if reason is provided
            if (isset($validated['reason'])) {
                $seller->admin_notes = $validated['reason'];
            }
        }

        // Regenerate slug if store name changes
        if (isset($validated['store_name']) && $validated['store_name'] !== $seller->store_name) {
            $validated['store_slug'] = $this->generateUniqueSlug($validated['store_name']);
        }

        // Handle file uploads - Remove if you're handling uploads separately
        // Note: Your React component seems to handle image uploads separately

        $seller->update($validated);

        return response()->json([
            'success' => true,
            'data' => $seller->fresh(),
            'message' => 'Seller profile updated successfully'
        ]);

    } catch (\Exception $e) {
        \Log::error('Failed to update seller profile: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update seller profile: ' . $e->getMessage()
        ], 500);
    }
}

public function updateMyStore(Request $request)
    {
        try {
            $user = auth()->user();
            $seller = SellerProfile::where('user_id', $user->id)->first();

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'store_name' => 'sometimes|string|max:255|unique:seller_profiles,store_name,'.$seller->id,
                'business_type' => 'sometimes|in:individual,company,retail,wholesale,manufacturer',
                'business_registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'description' => 'sometimes|string|min:100|max:2000',
                'contact_email' => 'sometimes|email|max:255',
                'contact_phone' => 'sometimes|string|max:20',
                'website' => 'nullable|url|max:255',
                'social_facebook' => 'nullable|url|max:255',
                'social_twitter' => 'nullable|url|max:255',
                'social_instagram' => 'nullable|url|max:255',
                'social_linkedin' => 'nullable|url|max:255',
                'social_youtube' => 'nullable|url|max:255',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'store_logo' => 'nullable|string|max:500',
                'store_banner' => 'nullable|string|max:500',
                'account_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'year_established' => 'nullable|integer|min:1900|max:'.date('Y'),
                'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
                'production_capacity' => 'nullable|string|max:255',
                'quality_certifications' => 'nullable|array',
                'quality_certifications.*' => 'string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Regenerate slug if store name changes
            if (isset($validated['store_name']) && $validated['store_name'] !== $seller->store_name) {
                $validated['store_slug'] = $this->generateUniqueSlug($validated['store_name']);
            }

            $seller->update($validated);

            return response()->json([
                'success' => true,
                'data' => $seller->fresh(),
                'message' => 'Store profile updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update store profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update store profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * Get current user's seller profile
 */
// public function myStore(Request $request)
// {
//     try {
//         $user = auth()->user();
        
//         \Log::info('myStore called for User ID: ' . $user->id);
        
//         $seller = SellerProfile::where('user_id', $user->id)->first();

//         if (!$seller) {
//             \Log::warning('No seller profile found for User ID: ' . $user->id);
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Seller profile not found. Please complete seller onboarding first.'
//             ], 404);
//         }

//         \Log::info('Seller profile found - ID: ' . $seller->id . ', Status: ' . $seller->status);

//         return response()->json([
//             'success' => true,
//             'data' => $seller
//         ]);

//     } catch (\Exception $e) {
//         \Log::error('Error in myStore: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to fetch seller profile: ' . $e->getMessage()
//         ], 500);
//     }
// }

    /**
     * Delete seller profile (admin only)
     */
    public function destroy($id)
    {
        try {
            $seller = SellerProfile::findOrFail($id);
            
            // Delete associated files
            if ($seller->store_logo) {
                Storage::disk('public')->delete($seller->store_logo);
            }
            if ($seller->store_banner) {
                Storage::disk('public')->delete($seller->store_banner);
            }
            if ($seller->certificate) {
                Storage::disk('public')->delete($seller->certificate);
            }

            $seller->delete();

            return response()->json([
                'success' => true,
                'message' => 'Seller profile deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get seller's products
     */
    public function sellerProducts($idOrSlug, Request $request)
    {
        try {
            $seller = SellerProfile::where('id', $idOrSlug)
                ->orWhere('store_slug', $idOrSlug)
                ->orWhere('store_id', $idOrSlug)
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'per_page' => 'sometimes|integer|min:1|max:100',
                'category_id' => 'sometimes|exists:categories,id',
                'min_price' => 'sometimes|numeric|min:0',
                'max_price' => 'sometimes|numeric|min:0',
                'sort' => 'sometimes|in:newest,price_asc,price_desc,rating,popular'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('per_page', 12);

            $query = Product::where('seller_id', $seller->user_id)
                ->where('is_active', true)
                ->with(['category', 'reviews'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews');

            // Apply filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Apply sorting
            switch ($request->input('sort', 'newest')) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'rating':
                    $query->orderBy('reviews_avg_rating', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('sold_count', 'desc');
                    break;
                default: // newest
                    $query->latest();
            }

            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'seller' => $seller,
                    'products' => $products
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Seller not found'
            ], 404);
        }
    }

    /**
     * Get seller's reviews
     */
    public function sellerReviews($idOrSlug, Request $request)
    {
        try {
            $seller = SellerProfile::where('id', $idOrSlug)
                ->orWhere('store_slug', $idOrSlug)
                ->orWhere('store_id', $idOrSlug)
                ->firstOrFail();

            $perPage = $request->input('per_page', 10);

            $reviews = SellerReview::where('seller_profile_id', $seller->id)
                ->where('status', 'approved')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'seller' => $seller,
                    'reviews' => $reviews
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Seller not found'
            ], 404);
        }
    }

    /**
     * Admin: Get all seller profiles for management
     */
    public function adminIndex(Request $request)
{
    $validator = Validator::make($request->all(), [
        'per_page' => 'sometimes|integer|min:1|max:100',
        'status' => 'sometimes|in:pending,approved,active,suspended,closed', // Updated status values
        'search' => 'sometimes|string|max:255'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $perPage = $request->input('per_page', 15);
    
    $query = SellerProfile::with(['user', 'reviews'])
        ->withAvg('reviews', 'rating')
        ->withCount('reviews');

    // Filter by status if provided
    if ($request->has('status') && !empty($request->status)) {
        $query->where('status', $request->status);
    }

    // Search filter
    if ($request->has('search') && !empty($request->search)) {
        $query->where(function($q) use ($request) {
            $q->where('store_name', 'like', '%'.$request->search.'%')
              ->orWhere('store_id', 'like', '%'.$request->search.'%')
              ->orWhere('contact_email', 'like', '%'.$request->search.'%')
              ->orWhereHas('user', function($userQuery) use ($request) {
                  $userQuery->where('name', 'like', '%'.$request->search.'%')
                           ->orWhere('email', 'like', '%'.$request->search.'%');
              });
        });
    }

    $sellers = $query->orderBy('created_at', 'desc')->paginate($perPage);

    return response()->json([
        'success' => true,
        'data' => $sellers
    ]);
}

    /**
     * Admin: Approve seller profile
     */
    public function adminApprove($id)
    {
        try {
            $seller = SellerProfile::findOrFail($id);
            $seller->update(['status' => 'approved']);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve seller profile'
            ], 500);
        }
    }

    /**
     * Admin: Reject seller profile
     */
    public function adminReject($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $seller = SellerProfile::findOrFail($id);
            $seller->update([
                'status' => 'rejected',
                'admin_notes' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject seller profile'
            ], 500);
        }
    }

    /**
     * Generate unique slug for store
     */
    private function generateUniqueSlug($storeName)
    {
        $slug = Str::slug($storeName);
        $originalSlug = $slug;
        $count = 1;

        while (SellerProfile::where('store_slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function getBusinessTypes()
    {
        $businessTypes = [
            [
                'value' => 'individual',
                'label' => 'Individual/Sole Proprietorship',
                'description' => 'A business owned and operated by one person',
                'requires_registration' => false,
            ],
            [
                'value' => 'company', 
                'label' => 'Private Limited Company',
                'description' => 'A registered company with limited liability',
                'requires_registration' => true,
            ],
            [
                'value' => 'partnership',
                'label' => 'Partnership',
                'description' => 'Business owned by two or more individuals',
                'requires_registration' => true,
            ],
            [
                'value' => 'cooperative',
                'label' => 'Cooperative',
                'description' => 'Member-owned business organization',
                'requires_registration' => true,
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $businessTypes
        ]);
    }

    public function debugSellerStatus(Request $request)
{
    try {
        $user = auth()->user();
        
        \Log::info('Debug Seller Status - User ID: ' . $user->id);
        \Log::info('User Type: ' . $user->type);
        
        $sellerProfile = SellerProfile::where('user_id', $user->id)->first();
        
        if ($sellerProfile) {
            \Log::info('Seller Profile Found - ID: ' . $sellerProfile->id . ', Status: ' . $sellerProfile->status);
            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'user_type' => $user->type,
                'seller_profile_exists' => true,
                'seller_id' => $sellerProfile->id,
                'seller_status' => $sellerProfile->status,
                'store_name' => $sellerProfile->store_name
            ]);
        } else {
            \Log::info('No Seller Profile Found for User ID: ' . $user->id);
            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'user_type' => $user->type,
                'seller_profile_exists' => false,
                'message' => 'No seller profile found for this user'
            ]);
        }
        
    } catch (\Exception $e) {
        \Log::error('Debug Seller Status Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Debug error: ' . $e->getMessage()
        ], 500);
    }
}

// Add this temporary test method to SellerController
public function testMyStore(Request $request)
{
    try {
        $user = auth()->user();
        
        \Log::info('testMyStore - User ID: ' . $user->id);
        \Log::info('testMyStore - User Roles: ' . json_encode($user->roles ?? []));
        
        $seller = SellerProfile::where('user_id', $user->id)->first();
        
        if ($seller) {
            return response()->json([
                'success' => true,
                'message' => 'Seller profile found',
                'data' => [
                    'seller_id' => $seller->id,
                    'store_name' => $seller->store_name,
                    'status' => $seller->status,
                    'user_type' => $user->type
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No seller profile'
            ], 404);
        }
        
    } catch (\Exception $e) {
        \Log::error('testMyStore error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}
}