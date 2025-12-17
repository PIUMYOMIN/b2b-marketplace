<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SellerProfile;
use App\Models\BusinessType;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone' => ['required', 'regex:/^(\+?959|09|9)\d{7,9}$/',   'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => 'required|in:buyer,seller',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated) {
            // Normalize Myanmar phone number
            $phone = $this->normalizeMyanmarPhone($validated['phone']);

            if (!$this->isValidMyanmarPhone($phone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Myanmar phone number format'
                ], 422);
            }

            // Generate sequential user_id
            $lastUser = User::withTrashed()->orderBy('id', 'desc')->first();
            $nextUserId = $lastUser ? str_pad($lastUser->id + 1, 6, '0',    STR_PAD_LEFT) : '000001';

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

            // Assign role
            $user->syncRoles([$validated['type']]);

            // If user is seller, create seller profile
            if ($validated['type'] === 'seller') {
                $storeName = $validated['name'] . "'s Store";
                $storeSlug = SellerProfile::generateStoreSlug($storeName);

                // Get default individual business type
                $defaultBusinessType = BusinessType::where('slug', 'individual')
                    ->where('is_active', true)
                    ->first();

                if (!$defaultBusinessType) {
                    throw new \Exception('Default business type not found');
                }

                SellerProfile::create([
                    'user_id' => $user->id,
                    'store_name' => $storeName,
                    'store_slug' => $storeSlug,
                    'store_id' => SellerProfile::generateStoreId(),
                    'business_type_id' => $defaultBusinessType->id,
                    'business_type' => $defaultBusinessType->slug,
                    'contact_email' => $user->email,
                    'contact_phone' => $user->phone,
                    'address' => $validated['address'] ?? '',
                    'city' => $validated['city'] ?? '',
                    'state' => $validated['state'] ?? '',
                    'country' => 'Myanmar',
                    'status' => SellerProfile::STATUS_SETUP_PENDING,
                    'verification_status' => 'pending',
                ]);

                Log::info('Seller profile created during registration', [
                    'user_id' => $user->id,
                    'business_type_id' => $defaultBusinessType->id,
                    'store_name' => $storeName,
                    'store_slug' => $storeSlug,
                    'status' => SellerProfile::STATUS_SETUP_PENDING
                ]);
            }

            // Generate API token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Reload user with roles
            $user->load('roles');

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'requires_onboarding' => $validated['type'] === 'seller'
                ]
            ], 201);
        });
    }

    /**
     * Normalize Myanmar phone number to +959 format
     */
    private function normalizeMyanmarPhone($phone)
    {
        // Remove any non-digit characters except +
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

        // Handle different Myanmar phone formats
        if (str_starts_with($cleanPhone, '09')) {
            // Format: 09xxxxxxxxx -> +959xxxxxxxxx
            return '+95' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '9') && !str_starts_with($cleanPhone, '95')) {
            // Format: 9xxxxxxxx -> +959xxxxxxxx
            return '+95' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '959')) {
            // Format: 959xxxxxxxx -> +959xxxxxxxx
            return '+' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '95') && !str_starts_with($cleanPhone, '959')) {
            // Format: 95xxxxxxxx -> +959xxxxxxxx
            return '+9' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '+959')) {
            // Already in correct format
            return $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '+95')) {
            // Format: +95xxxxxxxx -> +959xxxxxxxx
            return '+9' . substr($cleanPhone, 1);
        }

        // If no pattern matches, return as is (will be validated)
        return $cleanPhone;
    }

    /**
     * Validate Myanmar phone number after normalization
     */
    private function isValidMyanmarPhone($phone)
    {
        // Should start with +959 and have 7-9 digits after
        return preg_match('/^\+959\d{7,9}$/', $phone);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'regex:/^(\+?959|09|9)\d{7,9}$/'],
            'password' => 'required'
        ]);

        // Normalize Myanmar phone number to +959 format
        $phone = $this->normalizeMyanmarPhone($request->phone);

        // Validate normalized phone
        if (!$this->isValidMyanmarPhone($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Myanmar phone number format'
            ], 422);
        }

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