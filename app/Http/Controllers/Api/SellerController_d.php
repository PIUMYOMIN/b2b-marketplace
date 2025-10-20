<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\SellerReview;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class SellerController extends Controller
{
    /**
     * Get all sellers (public endpoint)
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
            'business_type' => 'sometimes|string|in:individual,company,retail,wholesale,manufacturer',
            'city' => 'sometimes|string|max:100',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'sort' => 'sometimes|in:newest,rating,name'
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
            ->withCount('reviews')
            ->where('status', 'approved');

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
        $user = $request->user();

        // Check if user is seller
        if (!$user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a seller'
            ], 403);
        }

        // ✅ Check if seller profile already exists
        $existingProfile = SellerProfile::where('user_id', $user->id)->first();
        if ($existingProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile already exists'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            // Store Basic Info
            'store_name' => 'required|string|max:255|unique:seller_profiles,store_name',
            'business_type' => 'required|in:individual,company,retail,wholesale,    manufacturer,service,partnership,private_limited,public_limited,cooperative',
            'description' => 'nullable|string|max:2000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'store_logo' => 'nullable|string|max:500',
            'store_banner' => 'nullable|string|max:500',

            // Business Details
            'business_registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'account_number' => 'nullable|string|max:255',
            'social_facebook' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',

            // Address Info
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',

            // Additional Info
            'year_established' => 'nullable|integer|min:1900|max:'.date('Y'),
            'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
            'production_capacity' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validated = $validator->validated();

            // ✅ Create new seller profile (first time creation)
            $sellerProfile = SellerProfile::create(array_merge($validated, [
                'user_id' => $user->id,
                'store_id' => SellerProfile::generateStoreId(),
                'store_slug' => SellerProfile::generateStoreSlug($validated['store_name']),
                'status' => SellerProfile::STATUS_PENDING // Ready for admin approval
            ]));

            Log::info('Seller profile created for user: ' . $user->id . ', Profile ID: ' .  $sellerProfile->id);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile created successfully and submitted for     approval',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create seller profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user has completed onboarding
     */
    public function getOnboardingStatus(Request $request)
    {
        try {
            $user = $request->user();

            $isSeller = $user->hasRole('seller') || $user->type === 'seller';

            if (!$isSeller) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_seller' => false,
                        'onboarding_complete' => false,
                        'needs_onboarding' => false,
                        'message' => 'User is not a seller'
                    ]
                ]);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            $onboardingComplete = $sellerProfile && in_array($sellerProfile->status, ['approved', 'active']);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_seller' => true,
                    'onboarding_complete' => $onboardingComplete,
                    'needs_onboarding' => !$onboardingComplete,
                    'profile_status' => $sellerProfile ? $sellerProfile->status : 'not_started',
                    'has_profile' => !!$sellerProfile,
                    'message' => $sellerProfile ? 
                        ($onboardingComplete ? 'Onboarding complete' : 'Profile pending approval') : 
                        'Onboarding not started'
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
     * Get current user's seller profile
     */
    public function myStore(Request $request)
{
    try {
        $user = $request->user();

        // Check if user is a seller
        if (!$user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'User is not registered as a seller'
            ], 403);
        }

        $sellerProfile = SellerProfile::where('user_id', $user->id)
            ->with(['user'])
            ->withAvg('reviews', 'rating')
            ->withCount(['reviews', 'products'])
            ->first();

        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found. Please complete seller onboarding first.',
                'needs_onboarding' => true
            ], 404);
        }

        // Get additional stats
        $stats = [
            'total_products' => $sellerProfile->products_count,
            'active_products' => Product::where('seller_id', $user->id)
                ->where('is_active', true)
                ->count(),
            'total_orders' => 0, // You'll need to implement this based on your Order model
            'pending_orders' => 0, // You'll need to implement this
            'total_revenue' => 0, // You'll need to implement this
            'average_rating' => $sellerProfile->reviews_avg_rating ? round($sellerProfile->reviews_avg_rating, 2) : 0,
            'total_reviews' => $sellerProfile->reviews_count
        ];

        // Format the response
        $responseData = array_merge($sellerProfile->toArray(), [
            'stats' => $stats,
            'is_approved' => $sellerProfile->status === 'approved' || $sellerProfile->status === 'active',
            'is_pending' => $sellerProfile->status === 'pending',
            'is_suspended' => $sellerProfile->status === 'suspended'
        ]);

        return response()->json([
            'success' => true,
            'data' => $responseData
        ]);

    } catch (\Exception $e) {
        \Log::error('Error in myStore: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch seller profile: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Update current user's store profile
     */
    public function updateMyStore(Request $request)
    {
        try {
            $user = $request->user();
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
     * Get seller details (public endpoint)
     */
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
     * Update seller profile (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $seller = SellerProfile::findOrFail($id);
            $user = auth()->user();

            // Authorization check - only admin can update any seller profile
            if (!$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update seller profiles'
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
                'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'certificate' => 'nullable|file|mimes:pdf,jpeg,png|max:5120',
                'location' => 'nullable|string|max:255',
                'year_established' => 'nullable|integer|min:1900|max:'.date('Y'),
                'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
                'production_capacity' => 'nullable|string|max:255',
                'quality_certifications' => 'nullable|array',
                'quality_certifications.*' => 'string|max:255',
                'status' => 'sometimes|in:pending,approved,rejected,suspended'
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

            // Handle file uploads
            if ($request->hasFile('store_logo')) {
                // Delete old logo
                if ($seller->store_logo) {
                    Storage::disk('public')->delete($seller->store_logo);
                }
                $validated['store_logo'] = $request->file('store_logo')->store(
                    'sellers/'.$seller->user_id.'/logo', 
                    'public'
                );
            }

            if ($request->hasFile('store_banner')) {
                // Delete old banner
                if ($seller->store_banner) {
                    Storage::disk('public')->delete($seller->store_banner);
                }
                $validated['store_banner'] = $request->file('store_banner')->store(
                    'sellers/'.$seller->user_id.'/banner', 
                    'public'
                );
            }

            if ($request->hasFile('certificate')) {
                // Delete old certificate
                if ($seller->certificate) {
                    Storage::disk('public')->delete($seller->certificate);
                }
                $validated['certificate'] = $request->file('certificate')->store(
                    'sellers/'.$seller->user_id.'/documents', 
                    'public'
                );
            }

            $seller->update($validated);

            return response()->json([
                'success' => true,
                'data' => $seller->fresh(),
                'message' => 'Seller profile updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

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
            'status' => 'sometimes|in:pending,approved,rejected,suspended',
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

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('store_name', 'like', '%'.$request->search.'%')
                  ->orWhere('store_id', 'like', '%'.$request->search.'%')
                  ->orWhere('contact_email', 'like', '%'.$request->search.'%');
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
     * Get business types
     */
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

    /**
     * Debug seller status
     */
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
}