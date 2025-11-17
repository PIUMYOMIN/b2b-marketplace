<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\SellerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

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
            'business_type' => 'sometimes|string|in:individual,company,retail,wholesale,manufacturer,partnership,cooperative',
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
        if ($request->has('search') && $request->search !== null) {
            $query->where(function($q) use ($request) {
                $q->where('store_name', 'like', '%'.$request->search.'%')
                  ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->has('business_type') && $request->business_type !== null) {
            $query->where('business_type', $request->business_type);
        }

        if ($request->has('city') && $request->city !== null) {
            $query->where('city', $request->city);
        }

        if ($request->has('min_rating') && $request->input('min_rating') !== null) {
            // use proper PHP request access and ensure numeric comparison
            $query->having('reviews_avg_rating', '>=', (float) $request->input('min_rating'));
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
        $user = $request->user();

        // Check if user is seller
        if (!isset($user->type) || $user->type !== 'seller') {
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
            'business_type' => 'required|in:individual,company,retail,wholesale,manufacturer,service,partnership,   private_limited,public_limited,cooperative',
            'description' => 'nullable|string|max:2000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',

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

            // Remove file fields from validated data as we'll handle them separately
            $fileFields = ['store_logo', 'store_banner'];
            $createData = array_diff_key($validated, array_flip($fileFields));

            // ✅ Create new seller profile (first time creation)
            $sellerProfile = SellerProfile::create(array_merge($createData, [
                'user_id' => $user->id,
                'store_id' => SellerProfile::generateStoreId(),
                'store_slug' => SellerProfile::generateStoreSlug($validated['store_name']),
                'status' => SellerProfile::STATUS_PENDING,
                'store_logo' => null,
                'store_banner' => null
            ]));

            // Handle store logo upload after profile creation
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $sellerProfile->id);
                if ($logoPath) {
                    $sellerProfile->update(['store_logo' => $logoPath]);
                }
            }

            // Handle store banner upload after profile creation
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $sellerProfile->id);
                if ($bannerPath) {
                    $sellerProfile->update(['store_banner' => $bannerPath]);
                }
            }

            Log::info('Seller profile created for user: ' . $user->id . ', Profile ID: ' . $sellerProfile->id);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile created successfully and submitted for approval',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create seller profile: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // If profile was created but file upload failed, delete the profile
            if (isset($sellerProfile)) {
                $sellerProfile->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload store logo to organized directory structure
     */
    private function uploadStoreLogo($file, $storeId)
    {
        try {
            $basePath = "store_profile/{$storeId}/store_logo";

            // Generate unique filename
            $filename = 'logo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Store the file
            $path = $file->storeAs($basePath, $filename, 'public');

            Log::info('Store logo uploaded: ' . $path);
            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to upload store logo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload store banner to organized directory structure
     */
    private function uploadStoreBanner($file, $storeId)
    {
        try {
            $basePath = "store_profile/{$storeId}/store_banner";

            // Generate unique filename
            $filename = 'banner_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Store the file
            $path = $file->storeAs($basePath, $filename, 'public');

            Log::info('Store banner uploaded: ' . $path);
            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to upload store banner: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update store basic information during onboarding
     */
    public function updateStoreBasic(Request $request)
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
                'store_name' => 'required|string|max:255|unique:seller_profiles,store_name,' . $sellerProfile->id,
                'business_type' => 'required|in:individual,company,retail,wholesale,manufacturer,service,partnership,   private_limited,public_limited,cooperative',
                'description' => 'nullable|string|max:2000',
                'contact_email' => 'required|email|max:255',
                'contact_phone' => 'required|string|max:20',
                'store_logo' => 'nullable|string|max:500',
                'store_banner' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Handle file uploads if provided
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $sellerProfile->id);
                if ($logoPath) {
                    $validated['store_logo'] = $logoPath;
                }
            }

            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $sellerProfile->id);
                if ($bannerPath) {
                    $validated['store_banner'] = $bannerPath;
                }
            }

            $sellerProfile->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Store basic information updated successfully',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update store basic info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update store basic information'
            ], 500);
        }
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
            'social_linkedin' => 'nullable|url|max:255',
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
    
        $sellerProfileData = $sellerProfile->toArray();
    
        // Convert store logo/banner to full URLs
        $sellerProfileData['store_logo'] = !empty($sellerProfileData['store_logo'])
            ? url('storage/' . ltrim($sellerProfileData['store_logo'], '/'))
            : null;
    
        $sellerProfileData['store_banner'] = !empty($sellerProfileData['store_banner'])
            ? url('storage/' . ltrim($sellerProfileData['store_banner'], '/'))
            : null;
    
        // Convert product images to full URLs
        if (isset($sellerProfileData['products']['data'])) {
            foreach ($sellerProfileData['products']['data'] as &$product) {
                if (isset($product['images'])) {
                    foreach ($product['images'] as &$image) {
                        if (!str_starts_with($image['url'], 'http')) {
                            $image['url'] = url('storage/' . ltrim($image['url'], '/'));
                        }
                    }
                }
            }
        }
    
        return response()->json([
            'success' => true,
            'data' => $sellerProfileData
        ]);
    }


    /**
     * Get seller details (public endpoint)
     */

    public function show($idOrSlug)
    {
        try {
            $seller = SellerProfile::where('id', $idOrSlug)
                ->orWhere('store_id', $idOrSlug)
                ->orWhere('store_slug', $idOrSlug)
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

            // Get seller stats - FIXED total_orders calculation
            $stats = [
                'total_products' => Product::where('seller_id', $seller->user_id)->count    (),
                'active_products' => Product::where('seller_id', $seller->user_id)
                    ->where('is_active', true)->count(),
                'total_orders' => \App\Models\Order::where('seller_id', $seller->user_id)   ->count(),
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
            \Log::error('Error in seller show: ' . $e->getMessage());
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
            $user = Auth::user();

            // Authorization check
            if ($user->id !== $seller->user_id && (!isset($user->type) || $user->type !== 'admin')) {
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
                'description' => 'nullable|string|max:2000',
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
                'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Updated validation
                'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Updated validation
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

            // Handle store logo upload
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $seller->id);
                if ($logoPath) {
                    // Delete old logo if exists
                    if ($seller->store_logo) {
                        Storage::disk('public')->delete($seller->store_logo);
                    }
                    $validated['store_logo'] = $logoPath;
                }
            }

            // Handle store banner upload
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $seller->id);
                if ($bannerPath) {
                    // Delete old banner if exists
                    if ($seller->store_banner) {
                        Storage::disk('public')->delete($seller->store_banner);
                    }
                    $validated['store_banner'] = $bannerPath;
                }
            }

            // Only admin can change status
            if (!isset($user->type) || $user->type !== 'admin' && isset($validated['status'])) {
                unset($validated['status']);
            }

            // Admin status update logic
            if (isset($user->type) && $user->type === 'admin' && isset($validated['status'])) {
                $seller->status = $validated['status'];

                // Optional: Store admin notes if reason is provided
                if (isset($validated['reason'])) {
                    $seller->admin_notes = $validated['reason'];
                }
            }

            // Regenerate slug if store name changes
            if (isset($validated['store_name']) && $validated['store_name'] !== $seller->store_name) {
                $validated['store_slug'] = SellerProfile::generateUniqueSlug($validated['store_name']);
            }

            $seller->update($validated);

            return response()->json([
                'success' => true,
                'data' => $seller->fresh(),
                'message' => 'Seller profile updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update seller profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

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
                'description' => 'nullable|string|max:2000',
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
                'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Updated validation
                'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Updated validation
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

            // Handle store logo upload
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $seller->id);
                if ($logoPath) {
                    // Delete old logo if exists
                    if ($seller->store_logo) {
                        Storage::disk('public')->delete($seller->store_logo);
                    }
                    $validated['store_logo'] = $logoPath;
                }
            }

            // Handle store banner upload
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $seller->id);
                if ($bannerPath) {
                    // Delete old banner if exists
                    if ($seller->store_banner) {
                        Storage::disk('public')->delete($seller->store_banner);
                    }
                    $validated['store_banner'] = $bannerPath;
                }
            }

            // Regenerate slug if store name changes
            if (isset($validated['store_name']) && $validated['store_name'] !== $seller->store_name) {
                $validated['store_slug'] = SellerProfile::generateUniqueSlug($validated['store_name']);
            }

            $seller->update($validated);

            // Update status to pending if onboarding is complete
            if ($seller->isOnboardingComplete()) {
                $seller->update(['status' => SellerProfile::STATUS_PENDING]);
            }

            return response()->json([
                'success' => true,
                'data' => $seller->fresh(),
                'message' => 'Store profile updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update store profile: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update store profile: ' . $e->getMessage()
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
            
            // Delete associated files and directories
            if ($seller->store_logo) {
                Storage::disk('public')->delete($seller->store_logo);
            }
            if ($seller->store_banner) {
                Storage::disk('public')->delete($seller->store_banner);
            }
            if ($seller->certificate) {
                Storage::disk('public')->delete($seller->certificate);
            }

            // Delete the entire store profile directory if it exists
            $storeProfilePath = "store_profile/{$seller->id}";
            if (Storage::disk('public')->exists($storeProfilePath)) {
                Storage::disk('public')->deleteDirectory($storeProfilePath);
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
                'value' => 'retail',
                'label' => 'Retail Business',
                'description' => 'Business that sells directly to consumers',
                'requires_registration' => true,
            ],
            [
                'value' => 'wholesale',
                'label' => 'Wholesale Business',
                'description' => 'Business that sells in bulk to retailers',
                'requires_registration' => true,
            ],
            [
                'value' => 'service',
                'label' => 'Service Business',
                'description' => 'Business that provides services rather than products',
                'requires_registration' => false,
            ]
        ];
    
        return response()->json([
            'success' => true,
            'data' => $businessTypes
        ]);
    }

    /**
     * Complete seller onboarding
     */
    public function completeOnboarding(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user is seller
            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }
        
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();
        
            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }
        
            $validator = Validator::make($request->all(), [
                // Store Basic Info
                'store_name' => 'required|string|max:255|unique:seller_profiles,store_name,' . $sellerProfile->id,
                // Use only the business types that exist in your database
                'business_type' => 'required|in:individual,company,retail,wholesale,service',
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
        
            $validated = $validator->validated();
        
            // Handle file uploads if provided as files
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $sellerProfile->id);
                if ($logoPath) {
                    $validated['store_logo'] = $logoPath;
                }
            } elseif ($request->has('store_logo') && is_string($request->store_logo)) {
                // If it's already a string path, use it directly
                $validated['store_logo'] = $request->store_logo;
            }
        
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $sellerProfile->id);
                if ($bannerPath) {
                    $validated['store_banner'] = $bannerPath;
                }
            } elseif ($request->has('store_banner') && is_string($request->store_banner)) {
                // If it's already a string path, use it directly
                $validated['store_banner'] = $request->store_banner;
            }
        
            // Update seller profile
            $sellerProfile->update($validated);
        
            // Update status to pending for admin approval
            $sellerProfile->update(['status' => SellerProfile::STATUS_PENDING]);
        
            Log::info('Seller onboarding completed for user: ' . $user->id . ', Profile ID: ' . $sellerProfile->id);
        
            return response()->json([
                'success' => true,
                'message' => 'Seller onboarding completed successfully and submitted for approval',
                'data' => $sellerProfile->fresh()
            ]);
        
        } catch (\Exception $e) {
            Log::error('Failed to complete seller onboarding: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete seller onboarding: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get seller onboarding status
     */
    public function getOnboardingStatus(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();
        
            if (!$sellerProfile) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_profile' => false,
                        'onboarding_complete' => false,
                        'current_step' => 'store-basic',
                        'status' => 'not_started'
                    ]
                ]);
            }
        
            return response()->json([
                'success' => true,
                'data' => [
                    'has_profile' => true,
                    'onboarding_complete' => $sellerProfile->isOnboardingComplete(),
                    'current_step' => $sellerProfile->getOnboardingStep(),
                    'status' => $sellerProfile->status,
                    'profile' => $sellerProfile
                ]
            ]);
        
        } catch (\Exception $e) {
            Log::error('Error getting onboarding status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get onboarding status'
            ], 500);
        }
    }

    /**
     * Get general sales summary (role-based)
     */
    public function salesSummary(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            // Get date range (default to last 30 days)
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

            // Total products
            $totalProducts = Product::where('seller_id', $user->id)->count();
            $activeProducts = Product::where('seller_id', $user->id)
                ->where('is_active', true)
                ->count();

            // Sales data
            $salesData = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(total_amount) as total_revenue'),
                    DB::raw('AVG(total_amount) as average_order_value')
                )
                ->first();

            // Total items sold
            $totalItemsSold = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $user->id)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->sum('order_items.quantity');

            // Order status counts
            $orderStatusCounts = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'status',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            // Recent sales trend (last 7 days)
            $recentSalesTrend = DB::table('orders')
                ->where('seller_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as orders_count')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top selling products
            $topProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $user->id)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select(
                    'products.id',
                    'products.name',
                    'products.images',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
                )
                ->groupBy('products.id', 'products.name', 'products.images')
                ->orderBy('total_sold', 'desc')
                ->limit(5)
                ->get();

            // Format top products with images
            $formattedTopProducts = $topProducts->map(function ($product) {
                $images = json_decode($product->images, true) ?? [];
                $primaryImage = collect($images)->firstWhere('is_primary', true) ?? $images[0] ?? null;
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $primaryImage['url'] ?? null,
                    'total_sold' => $product->total_sold,
                    'total_revenue' => $product->total_revenue,
                ];
            });

            $summary = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1
                ],
                'products' => [
                    'total' => $totalProducts,
                    'active' => $activeProducts,
                    'inactive' => $totalProducts - $activeProducts
                ],
                'sales' => [
                    'total_orders' => $salesData->total_orders ?? 0,
                    'total_items_sold' => $totalItemsSold ?? 0,
                    'total_revenue' => $salesData->total_revenue ?? 0,
                    'average_order_value' => $salesData->average_order_value ?? 0,
                    'revenue_formatted' => number_format($salesData->total_revenue ?? 0, 2) . ' MMK'
                ],
                'orders_by_status' => $orderStatusCounts,
                'recent_trend' => $recentSalesTrend,
                'top_products' => $formattedTopProducts
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller salesSummary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top products (role-based)
     */
    public function topProducts(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $limit = $request->input('limit', 5);
            $days = $request->input('days', 30);

            $topProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $user->id)
                ->where('orders.created_at', '>=', Carbon::now()->subDays($days))
                ->select(
                    'products.id',
                    'products.name',
                    'products.price',
                    'products.images',
                    'products.sku',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue'),
                    DB::raw('AVG(order_items.rating) as average_rating')
                )
                ->groupBy('products.id', 'products.name', 'products.price', 'products.images', 'products.sku')
                ->orderBy('total_sold', 'desc')
                ->limit($limit)
                ->get();

            // Format products with images and additional data
            $formattedProducts = $topProducts->map(function ($product) {
                $images = json_decode($product->images, true) ?? [];
                $primaryImage = collect($images)->firstWhere('is_primary', true) ?? $images[0] ?? null;
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'sku' => $product->sku,
                    'image' => $primaryImage['url'] ?? null,
                    'total_sold' => $product->total_sold,
                    'total_revenue' => $product->total_revenue,
                    'average_rating' => $product->average_rating ? round($product->average_rating, 2) : null,
                    'performance' => $this->calculatePerformance($product->total_sold)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller topProducts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent orders (role-based)
     */
    public function recentOrders(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $limit = $request->input('limit', 10);

            // Get recent orders with order items and buyer info
            $recentOrders = Order::where('seller_id', $user->id)
                ->with(['buyer:id,name,email', 'items.product:id,name'])
                ->select([
                    'id', 'order_number', 'status', 'total_amount', 'created_at',
                    'buyer_id', 'payment_status', 'payment_method'
                ])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'total_amount_formatted' => 'MMK ' . number_format($order->total_amount, 2),
                        'payment_status' => $order->payment_status,
                        'payment_method' => $order->payment_method,
                        'created_at' => $order->created_at->format('M j, Y g:i A'),
                        'buyer' => $order->buyer ? [
                            'name' => $order->buyer->name,
                            'email' => $order->buyer->email
                        ] : null,
                        'items_count' => $order->items->count(),
                        'products' => $order->items->take(2)->map(function ($item) {
                            return [
                                'name' => $item->product->name ?? $item->product_name,
                                'quantity' => $item->quantity,
                                'price' => $item->price
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $recentOrders
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller recentOrders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent orders: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get performance metrics (role-based)
     */
    public function performanceMetrics(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $days = $request->input('days', 30);

            // Overall metrics
            $totalProducts = Product::where('seller_id', $user->id)->count();
            $totalOrders = Order::where('seller_id', $user->id)->count();
            $totalRevenue = Order::where('seller_id', $user->id)
                ->where('status', 'delivered')
                ->sum('total_amount');

            // Recent performance (last 30 days)
            $recentOrders = Order::where('seller_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays($days))
                ->count();

            $recentRevenue = Order::where('seller_id', $user->id)
                ->where('status', 'delivered')
                ->where('created_at', '>=', Carbon::now()->subDays($days))
                ->sum('total_amount');

            // Rating and reviews
            $averageRating = DB::table('reviews')
                ->join('products', 'reviews.product_id', '=', 'products.id')
                ->where('products.seller_id', $user->id)
                ->avg('reviews.rating');

            $totalReviews = DB::table('reviews')
                ->join('products', 'reviews.product_id', '=', 'products.id')
                ->where('products.seller_id', $user->id)
                ->count();

            $metrics = [
                'overall' => [
                    'total_products' => $totalProducts,
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'total_revenue_formatted' => 'MMK ' . number_format($totalRevenue, 2)
                ],
                'recent' => [
                    'period_days' => $days,
                    'orders_count' => $recentOrders,
                    'revenue' => $recentRevenue,
                    'revenue_formatted' => 'MMK ' . number_format($recentRevenue, 2)
                ],
                'ratings' => [
                    'average_rating' => $averageRating ? round($averageRating, 2) : 0,
                    'total_reviews' => $totalReviews
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller performanceMetrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to calculate product performance
     */
    private function calculatePerformance($totalSold)
    {
        if ($totalSold >= 100) return 'excellent';
        if ($totalSold >= 50) return 'good';
        if ($totalSold >= 20) return 'average';
        return 'low';
    }

/**
 * Helper method to get orders by status
 */
protected function getOrdersByStatus($orders)
{
    return $orders->groupBy('status')
        ->map(function ($statusOrders, $status) use ($orders) {
            return [
                'count' => $statusOrders->count(),
                'revenue' => $statusOrders->sum('total_amount'),
                'percentage' => $orders->count() > 0 ? 
                    round(($statusOrders->count() / $orders->count()) * 100, 2) : 0
            ];
        });
}

/**
 * Get sales by day with role-based filtering
 */
protected function getSalesByDay($start, $end, $user)
{
    $salesQuery = Order::whereBetween('created_at', [$start, $end])
        ->where('status', 'delivered');

    // Role-based filtering
    if (isset($user->type) && $user->type === 'seller') {
        $salesQuery->where('seller_id', $user->id);
    } elseif (isset($user->type) && $user->type === 'buyer') {
        $salesQuery->where('buyer_id', $user->id);
    }

    $sales = $salesQuery->selectRaw('DATE(created_at) as date, count(*) as count, sum(total_amount) as revenue')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

    // Fill missing days with zero values
    $results = [];
    $current = $start->copy();
    while ($current <= $end) {
        $date = $current->format('Y-m-d');
        $sale = $sales->firstWhere('date', $date);
        
        $results[] = [
            'date' => $date,
            'count' => $sale ? $sale->count : 0,
            'revenue' => $sale ? floatval($sale->revenue) : 0
        ];
        
        $current->addDay();
    }

    return $results;
}
}