<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone' => ['required', 'regex:/^(\+?95|0|9)\d{7,10}$/', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => 'required|in:buyer,seller', // ✅ Using 'type' field
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            // Normalize Myanmar phone number to +95 format
            $phone = $this->normalizeMyanmarPhone($request->phone);

            // Generate sequential user_id
            $lastUser = User::withTrashed()->orderBy('id', 'desc')->first();
            $nextUserId = $lastUser ? str_pad($lastUser->id + 1, 6, '0', STR_PAD_LEFT) : '000001';

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email ?? null,
                'phone' => $phone,
                'password' => Hash::make($request->password),
                'type' => $request->type, // ✅ Store the type field
                'address' => $request->address ?? null,
                'city' => $request->city ?? null,
                'state' => $request->state ?? null,
                'user_id' => $nextUserId,
                'status' => 'active',
                'is_active' => true,
            ]);

            // ✅ Assign role based on type field
            $user->assignRole($request->type); // 'buyer' or 'seller'

            // ✅ DO NOT create seller profile during registration
            if ($request->type === 'seller') {
                Log::info('Seller user registered, profile will be created during onboarding: ' . $user->id);
            }

            // Generate API token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user->load('roles'),
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

            // Check if onboarding is complete
            $onboardingComplete = $sellerProfile->isOnboardingComplete() && 
                                 in_array($sellerProfile->status, ['approved', 'active']);
            
            // Get current step for incomplete onboarding
            $currentStep = 'complete';
            if (!$onboardingComplete) {
                $currentStep = $sellerProfile->getOnboardingStep();
            }

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
                        'Onboarding in progress'
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
}