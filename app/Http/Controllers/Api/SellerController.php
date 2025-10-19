<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:255|unique:seller_profiles',
            'business_type' => 'required|in:individual,company,retail,wholesale,manufacturer',
            'business_registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'description' => 'required|string|min:100|max:2000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'website' => 'nullable|url|max:255',
            'social_facebook' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',
            'social_youtube' => 'nullable|url|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'certificate' => 'nullable|file|mimes:pdf,jpeg,png|max:5120',
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

        try {
            $user = auth()->user();
            
            // Check if user already has a seller profile
            if ($user->sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a seller profile'
                ], 409);
            }

            $validated = $validator->validated();
            
            // Generate store slug and ID
            $validated['store_slug'] = $this->generateUniqueSlug($validated['store_name']);
            $validated['store_id'] = 'STORE-' . strtoupper(Str::random(10));
            $validated['user_id'] = $user->id;
            $validated['status'] = 'pending'; // Requires admin approval

            // Handle file uploads
            if ($request->hasFile('store_logo')) {
                $validated['store_logo'] = $request->file('store_logo')->store(
                    'sellers/'.$user->id.'/logo', 
                    'public'
                );
            }

            if ($request->hasFile('store_banner')) {
                $validated['store_banner'] = $request->file('store_banner')->store(
                    'sellers/'.$user->id.'/banner', 
                    'public'
                );
            }

            if ($request->hasFile('certificate')) {
                $validated['certificate'] = $request->file('certificate')->store(
                    'sellers/'.$user->id.'/documents', 
                    'public'
                );
            }

            // Create seller profile
            $sellerProfile = SellerProfile::create($validated);

            // Update user type to seller
            $user->update(['type' => 'seller']);

            return response()->json([
                'success' => true,
                'data' => $sellerProfile,
                'message' => 'Seller profile created successfully. Waiting for admin approval.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
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
     * Display a listing of the seller's products.
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

    public function adminIndex(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:pending,approved,active,suspended,closed', //     Updated status values
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
     * Update the specified resource in storage.
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
                'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'certificate' => 'nullable|file|mimes:pdf,jpeg,png|max:5120',
                'location' => 'nullable|string|max:255',
                'year_established' => 'nullable|integer|min:1900|max:'.date('Y'),
                'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
                'production_capacity' => 'nullable|string|max:255',
                'quality_certifications' => 'nullable|array',
                'quality_certifications.*' => 'string|max:255',
                'status' => 'sometimes|in:pending,approved,rejected,suspended,closed',
                'reason' => 'sometimes|string|max:1000' // If you want to track reasons
            ]);

                if ($user->hasRole('admin') && isset($validated['status'])) {
                    $seller->status = $validated['status'];
    
                // Optional: Store admin notes if reason is provided
                if (isset($validated['reason'])) {
                    $seller->admin_notes = $validated['reason'];
                }
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Only admin can change status
            if (!$user->hasRole('admin') && isset($validated['status'])) {
                unset($validated['status']);
            }

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
     * Remove the specified resource from storage.
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
     * Get the onboarding status of the authenticated seller.
     */

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
     * Get the authenticated seller's store profile.
     */
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
}