<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

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
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated) {
            // Normalize Myanmar phone number to +95 format
            $phone = $this->normalizeMyanmarPhone($validated['phone']);

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
            ]);

            // Assign role based on type
            $user->assignRole($validated['type']);

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
}