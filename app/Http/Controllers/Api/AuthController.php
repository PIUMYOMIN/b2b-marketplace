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
                $defaultBusinessType = BusinessType::where('slug_en', 'individual')
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
                    'onboarding_status' => 'pending',
                    'current_step' => 'store-basic',
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
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($cleanPhone, '09')) {
            return '+95' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '9') && !str_starts_with($cleanPhone, '95')) {
            return '+95' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '959')) {
            return '+' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '95')) {
            return '+95' . substr($cleanPhone, 2);
        } elseif (str_starts_with($cleanPhone, '+959')) {
            return $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '+95')) {
            return '+95' . substr($cleanPhone, 3);
        }

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
        $types = BusinessType::active()
            ->ordered()
            ->get()
            ->map(function ($type) {
                return [
                    'value' => $type->slug,
                    'label' => $type->name,
                    'description' => $type->description,
                    'requires_registration' => $type->requires_registration,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

}