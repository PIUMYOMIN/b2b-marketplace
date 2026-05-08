<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Auth\Events\Registered;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\SellerProfile;
use App\Models\BusinessType;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Notifications\NewUserRegistered;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use App\Rules\Recaptcha;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
            'phone' => ['required', 'regex:/^(\+?959|09|9)\d{7,9}$/', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => 'required|in:buyer,seller',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'recaptcha_token' => ['required', new Recaptcha],
            'ref_code' => 'nullable|string|max:12',
        ]);

        return DB::transaction(function () use ($validated) {
            // Normalize Myanmar phone number
            $phone = $this->normalizeMyanmarPhone($validated['phone']);

            if (!$this->isValidMyanmarPhone($phone)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.auth.invalid_phone')
                ], 422);
            }

            // Generate sequential user_id
            $lastUser = User::withTrashed()->orderBy('id', 'desc')->first();
            $nextUserId = $lastUser ? str_pad($lastUser->id + 1, 6, '0', STR_PAD_LEFT) : '000001';

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
                'referred_by' => isset($validated['ref_code'])
                    ? User::where('ref_code', $validated['ref_code'])->value('id')
                    : null,
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

            if ($user->email) {
                event(new Registered($user));
            }

            // Notify all admins of new registration
            try {
                $admins = User::where('type', 'admin')->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new NewUserRegistered($user));
                }
            } catch (\Exception $e) {
                Log::warning('Admin new-user notification failed: ' . $e->getMessage());
            }

            // Generate API token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Reload user with roles
            $user->load('roles');

            return response()->json([
                'success' => true,
                'message' => __('messages.auth.register_success'),
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'requires_onboarding' => $validated['type'] === 'seller',
                    'email_verification_required' => true
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
            'password' => 'required',
            'remember' => 'nullable|boolean',
            'recaptcha_token' => ['required', new Recaptcha],
            'ref_code' => 'nullable|string|max:12',
        ]);

        $phone = $this->normalizeMyanmarPhone($request->phone);

        if (!$this->isValidMyanmarPhone($phone)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.auth.invalid_phone')
            ], 422);
        }

        $user = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.auth.invalid_credentials')
            ], 401);
        }

        // Note: use 401 (not 403) to avoid revealing whether the account
        // exists. Only show 'inactive' detail after a valid captcha pass.
        if (!$user->is_active || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => __('messages.auth.invalid_credentials')
            ], 401);
        }

        // Set token expiration based on remember me
        $expiration = $request->boolean('remember')
            ? Carbon::now()->addDays(30)   // 30 days for "remember me"
            : Carbon::now()->addHours(2);  // 2 hours for normal session

        $token = $user->createToken('auth_token', ['*'], $expiration);

        return response()->json([
            'success' => true,
            'message' => __('messages.auth.login_success'),
            'data' => [
                'user' => $user->load('roles'),
                'token' => $token->plainTextToken
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
            'message' => __('messages.auth.logout_success')
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
                        'message' => __('messages.seller.not_a_seller')
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
                        'message' => __('messages.seller.profile_not_created')
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
                'message' => __('messages.general.server_error')
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

    // =========================================================================
    // Social / OAuth Authentication
    // =========================================================================
    // Provider-agnostic design: adding Facebook (or any future provider) only
    // requires a new private verify*Token() method below. Routes, middleware,
    // the registration flow, and the frontend button all stay identical.
    //
    // Supported providers: google | facebook (coming soon)
    //
    // Routes:
    //   POST /auth/{provider}           → handleSocialToken()
    //   POST /auth/{provider}/complete  → completeSocialRegistration()
    // =========================================================================

    /**
     * Step 1 — Verify a social token and either log the user in
     * or issue a short-lived "pending" token for role selection.
     *
     * Body: { credential: string, token_type?: "id_token"|"access_token" }
     */
    public function handleSocialToken(Request $request, string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $request->validate([
            'credential' => 'required|string',
            'token_type' => 'nullable|in:id_token,access_token',
        ]);

        $socialUser = $this->verifySocialToken(
            $provider,
            $request->credential,
            $request->input('token_type', 'id_token')
        );

        if (!$socialUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please try again.',
            ], 401);
        }

        $socialId = $socialUser['sub'] ?? $socialUser['id'] ?? null;
        $email    = $socialUser['email']   ?? null;
        $name     = $socialUser['name']    ?? 'User';
        $avatar   = $socialUser['picture'] ?? $socialUser['avatar'] ?? null;

        // Case 1: already linked to this social account
        $user = User::where('social_id', $socialId)
                    ->where('social_provider', $provider)
                    ->first();

        if ($user) {
            return $this->issueSocialToken($user, 'authenticated');
        }

        // Case 2: email already exists — link the social account
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update([
                    'social_id'       => $socialId,
                    'social_provider' => $provider,
                    'profile_photo'   => $user->profile_photo ?? $avatar,
                ]);
                return $this->issueSocialToken($user, 'authenticated');
            }
        }

        // Case 3: brand-new user — create pending record, ask for role/contact completion
        // Keep compatibility with DBs where users.phone is still NOT NULL.
        // We save a temporary unique placeholder and replace it in step 2.
        $tempPhone = 'pending-social-' . $this->nextUserId() . '-' . substr((string) Str::uuid(), 0, 8);
        $pending = User::create([
            'name'            => $name,
            'email'           => $email,
            'phone'           => $tempPhone,
            'password'        => Hash::make(Str::random(32)),
            'social_id'       => $socialId,
            'social_provider' => $provider,
            'profile_photo'   => $avatar,
            'type'            => 'pending',
            'user_id'         => $this->nextUserId(),
            'status'          => 'active',
            'is_active'       => true,
        ]);

        $missingFields = $this->getMissingSocialFields($pending);

        // 15-minute scoped token — only /auth/{provider}/complete accepts it
        $tempToken = $pending->createToken(
            'social-pending',
            ['social-pending'],
            Carbon::now()->addMinutes(15)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'status'  => 'needs_role',
            'data'    => [
                'temp_token'  => $tempToken,
                'provider'    => $provider,
                'social_user' => [
                    'name'   => $name,
                    'email'  => $email,
                    'avatar' => $avatar,
                ],
                'missing_fields' => $missingFields,
            ],
        ]);
    }

    /**
     * Step 2 — Assign role and complete social registration.
     * Requires the short-lived pending token from step 1.
     *
     * Body: { role: "buyer"|"seller", email?: string, phone: string }
     */
    public function completeSocialRegistration(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'pending' || !$user->social_provider) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only for new social-authenticated users.',
            ], 403);
        }

        return DB::transaction(function () use ($request, $user) {
            $role = $request->role;
            $missing = $this->getMissingSocialFields($user);

            $rules = [
                'role'  => 'required|in:buyer,seller',
                'phone' => ['required', 'regex:/^(\+?959|09|9)\d{7,9}$/', 'unique:users,phone,' . $user->id],
            ];

            if (in_array('email', $missing)) {
                $rules['email'] = 'required|string|email|max:255|unique:users,email,' . $user->id;
            } else {
                $rules['email'] = 'nullable|string|email|max:255|unique:users,email,' . $user->id;
            }

            $validated = $request->validate($rules);

            $phone = $this->normalizeMyanmarPhone($validated['phone']);
            if (!$this->isValidMyanmarPhone($phone)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.auth.invalid_phone')
                ], 422);
            }

            $user->update([
                'type'   => $role,
                'status' => 'active',
                'email'  => $validated['email'] ?? $user->email,
                'phone'  => $phone,
            ]);
            $user->syncRoles([$role]);

            if ($role === 'seller') {
                $storeName = $user->name . "'s Store";
                $defaultBt = BusinessType::where('slug_en', 'individual')
                    ->where('is_active', true)
                    ->firstOrFail();

                SellerProfile::create([
                    'user_id'             => $user->id,
                    'store_name'          => $storeName,
                    'store_slug'          => SellerProfile::generateStoreSlug($storeName),
                    'store_id'            => SellerProfile::generateStoreId(),
                    'business_type_id'    => $defaultBt->id,
                    'business_type'       => $defaultBt->slug,
                    'contact_email'       => $user->email,
                    'contact_phone'       => $user->phone,
                    'country'             => 'Myanmar',
                    'status'              => SellerProfile::STATUS_SETUP_PENDING,
                    'onboarding_status'   => 'pending',
                    'current_step'        => 'store-basic',
                    'verification_status' => 'pending',
                ]);
            }

            // Revoke the short-lived pending token
            $user->currentAccessToken()->delete();

            // Send OTP verification email
            if ($user->email && !$user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }

            // Notify admins
            try {
                $admins = User::where('type', 'admin')->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new NewUserRegistered($user));
                }
            } catch (\Exception $e) {
                Log::warning('Admin notification failed after social registration: ' . $e->getMessage());
            }

            $token = $user->createToken(
                'auth_token', ['*'], Carbon::now()->addHours(2)
            )->plainTextToken;

            $user->load('roles');

            return response()->json([
                'success' => true,
                'status'  => 'registered',
                'data'    => [
                    'token'                       => $token,
                    'user'                        => $user,
                    'requires_onboarding'         => $role === 'seller',
                    'email_verification_required' => (bool) $user->email && !$user->hasVerifiedEmail(),
                ],
            ], 201);
        });
    }

    // ── Social auth helpers ───────────────────────────────────────────────────

    /** Reject unsupported providers early with a clear error. */
    private function validateProvider(string $provider): void
    {
        $supported = ['google', 'facebook'];
        if (!in_array($provider, $supported)) {
            abort(404, "Provider [{$provider}] is not supported.");
        }
    }

    /**
     * Route to the correct token verifier based on provider.
     * Adding Facebook = add verifyFacebookToken() here and below.
     */
    private function verifySocialToken(string $provider, string $token, string $tokenType): ?array
    {
        return match ($provider) {
            'google'   => $this->verifyGoogleToken($token, $tokenType),
            'facebook' => $this->verifyFacebookToken($token),
            default    => null,
        };
    }

    /**
     * Verify a Google credential.
     * token_type "access_token" → userinfo endpoint  (useGoogleLogin flow)
     * token_type "id_token"     → tokeninfo endpoint (One Tap / credential flow)
     */
    private function verifyGoogleToken(string $token, string $tokenType = 'id_token'): ?array
    {
        try {
            if ($tokenType === 'access_token') {
                $response = Http::timeout(5)
                    ->withToken($token)
                    ->get('https://www.googleapis.com/oauth2/v3/userinfo');
            } else {
                $response = Http::timeout(5)
                    ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $token]);

                if ($response->successful()) {
                    $payload  = $response->json();
                    $clientId = config('services.google.client_id');
                    if ($clientId && ($payload['aud'] ?? '') !== $clientId) {
                        Log::warning('Google token audience mismatch', ['aud' => $payload['aud'] ?? null]);
                        return null;
                    }
                    if (($payload['exp'] ?? 0) < time()) return null;
                    return $payload;
                }
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Google token verification error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify a Facebook user access token.
     * Uses Facebook's debug_token endpoint — fill in when Facebook login ships.
     */
    private function verifyFacebookToken(string $token): ?array
    {
        try {
            $appId     = config('services.facebook.client_id');
            $appSecret = config('services.facebook.client_secret');
            $appToken  = "{$appId}|{$appSecret}";

            // Validate the token
            $debug = Http::timeout(5)->get('https://graph.facebook.com/debug_token', [
                'input_token'  => $token,
                'access_token' => $appToken,
            ]);

            if (!$debug->successful() || !($debug->json('data.is_valid') ?? false)) {
                return null;
            }

            // Fetch the user's profile
            $profile = Http::timeout(5)->get('https://graph.facebook.com/me', [
                'access_token' => $token,
                'fields'       => 'id,name,email,picture.type(large)',
            ]);

            if (!$profile->successful()) return null;

            $data = $profile->json();
            return [
                'sub'     => $data['id'],
                'name'    => $data['name']  ?? null,
                'email'   => $data['email'] ?? null,
                'picture' => $data['picture']['data']['url'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Facebook token verification error: ' . $e->getMessage());
            return null;
        }
    }

    /** Issue a full-session token and return an "authenticated" response. */
    private function issueSocialToken(User $user, string $status): JsonResponse
    {
        if (!$user->is_active || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => __('messages.auth.invalid_credentials'),
            ], 401);
        }

        $token = $user->createToken(
            'auth_token', ['*'], Carbon::now()->addHours(2)
        )->plainTextToken;

        $user->load('roles');

        return response()->json([
            'success' => true,
            'status'  => $status,
            'data'    => [
                'token'                       => $token,
                'user'                        => $user,
                'email_verification_required' => (bool) $user->email && !$user->hasVerifiedEmail(),
            ],
        ]);
    }

    private function nextUserId(): string
    {
        $last = User::withTrashed()->orderBy('id', 'desc')->first();
        return $last ? str_pad($last->id + 1, 6, '0', STR_PAD_LEFT) : '000001';
    }

    private function getMissingSocialFields(User $user): array
    {
        $missing = [];

        if (blank($user->email)) {
            $missing[] = 'email';
        }
        if (blank($user->phone)) {
            $missing[] = 'phone';
        }

        return $missing;
    }
}