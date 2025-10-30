<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SellerProfile;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone' => ['required', 'regex:/^(\+?95|0|9)\d{7,10}$/', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => 'required|in:buyer,seller',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string'
        ]);
    
        return DB::transaction(function () use ($validated) {
            // Normalize Myanmar phone number to +95 format
            $phone = $this->normalizeMyanmarPhone($validated['phone']);
        
            // Generate sequential user_id
            $lastUser = User::withTrashed()->orderBy('id', 'desc')->first();
            $nextUserId = $lastUser ? str_pad($lastUser->id + 1, 6, '0', STR_PAD_LEFT) :    '000001';
        
            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $phone,
                'password' => Hash::make($validated['password']),
                'type' => $validated['type'],
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'user_id' => $nextUserId,
                'status' => 'active',
                'is_active' => true,
            ]);
        
            // ✅ FIX: Proper role assignment
            $roleName = $validated['type']; // 'seller' or 'buyer'
            
            // Assign role using syncRoles to ensure only one role
            $user->syncRoles([$roleName]);
        
            // If user is seller, create seller profile with proper name
            if ($validated['type'] === 'seller') {
                // Generate store name from user's name
                $storeName = $validated['name'] . "'s Store";

                SellerProfile::create([
                    'user_id' => $user->id,
                    'store_name' => $storeName,
                    'store_slug' => SellerProfile::generateStoreSlug($storeName),
                    'store_id' => SellerProfile::generateStoreId(),
                    'business_type' => $user->business_type,
                    'contact_email' => $user->email,
                    'contact_phone' => $user->phone,
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'country' => 'Myanmar',
                    'status' => 'setup_pending',
                ]);
            }
        
            // Generate API token
            $token = $user->createToken('auth_token')->plainTextToken;
        
            // ✅ Reload user with roles to ensure they're included in response
            $user->load('roles');
        
            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
        });
    }


    /**
     * Normalize Myanmar phone number to +95 format
     */
    private function normalizeMyanmarPhone($phone)
    {
        // Remove any non-digit characters except +
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

        // Handle different Myanmar phone formats
        if (str_starts_with($cleanPhone, '0')) {
            // Format: 09xxxxxxxxx -> +959xxxxxxxxx
            return '+95' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '9')) {
            // Format: 9xxxxxxxxx -> +959xxxxxxxxx
            return '+95' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '95')) {
            // Format: 95xxxxxxxxx -> +95xxxxxxxxx
            return '+' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '959')) {
            // Format: 959xxxxxxxxx -> +959xxxxxxxxx
            return '+' . $cleanPhone;
        } elseif (!str_starts_with($cleanPhone, '+')) {
            // If it doesn't start with +, add it
            return '+' . $cleanPhone;
        }

        return $cleanPhone;
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'regex:/^(\+?95|0|9)\d{7,10}$/'],
            'password' => 'required'
        ]);

        // Normalize Myanmar phone number to +95 format
        $phone = $this->normalizeMyanmarPhone($request->phone);

        // Find user by normalized phone
        $user = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active'
            ], 403);
        }

        // Create API token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('roles'),
                'token' => $token
            ]
        ]);
    }

    public function refreshToken(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        $token = $request->user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()->load('roles'),
                'token' => $token
            ]
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('roles')
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Check seller onboarding status
     */
    public function getOnboardingStatus(Request $request)
{
    try {
        $user = $request->user();
        
        // ✅ Check if user is seller using both type field and role
        $isSeller = $user->type === 'seller' || $user->hasRole('seller');
        
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

        // ✅ If no seller profile exists, onboarding hasn't started
        if (!$sellerProfile) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_seller' => true,
                    'has_profile' => false,
                    'onboarding_complete' => false,
                    'needs_onboarding' => true,
                    'current_step' => 'store-basic',
                    'profile_status' => 'not_created',
                    'message' => 'Seller profile not created yet - start onboarding'
                ]
            ]);
        }

        // Check if onboarding is complete - be more strict
        $onboardingComplete = $sellerProfile->isOnboardingComplete() && 
                             in_array($sellerProfile->status, ['approved', 'active']);
        
        // Get current step for incomplete onboarding
        $currentStep = $sellerProfile->getOnboardingStep();

        // Debug info
        Log::info("Onboarding debug - User: {$user->id}, Store Name: '{$sellerProfile->store_name}', Business Type: '{$sellerProfile->business_type}', Step: {$currentStep}");

        return response()->json([
            'success' => true,
            'data' => [
                'is_seller' => true,
                'has_profile' => true,
                'onboarding_complete' => $onboardingComplete,
                'needs_onboarding' => !$onboardingComplete,
                'current_step' => $currentStep,
                'profile_status' => $sellerProfile->status,
                'profile' => $sellerProfile,
                'debug' => [ // Add debug info
                    'store_name_empty' => empty(trim($sellerProfile->store_name)),
                    'business_type_empty' => empty(trim($sellerProfile->business_type)),
                    'address_empty' => empty(trim($sellerProfile->address)),
                ],
                'message' => $onboardingComplete ? 
                    'Onboarding complete' : 
                    'Onboarding in progress - current step: ' . $currentStep
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error in getOnboardingStatus: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to get onboarding status'
        ], 500);
    }
}

    /**
     * Get business types for onboarding
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
                'value' => 'partnership', 
                'label' => 'Partnership',
                'description' => 'Business owned by two or more individuals',
                'requires_registration' => true,
            ],
            [
                'value' => 'private_limited',
                'label' => 'Private Limited Company',
                'description' => 'A registered company with limited liability',
                'requires_registration' => true,
            ],
            [
                'value' => 'public_limited',
                'label' => 'Public Limited Company',
                'description' => 'A company whose shares are traded publicly',
                'requires_registration' => true,
            ],
            [
                'value' => 'cooperative',
                'label' => 'Cooperative',
                'description' => 'Member-owned business organization',
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

    // In AuthController.php - Add debug endpoint
public function debugRoles(Request $request)
{
    $user = $request->user();
    
    return response()->json([
        'success' => true,
        'data' => [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'roles' => $user->getRoleNames()->toArray(),
            'has_seller_role' => $user->hasRole('seller'),
            'all_roles' => Role::all()->pluck('name'),
            'user_roles_table' => DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->get()
        ]
    ]);
}
}