<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => 'required|in:buyer,seller,admin',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string'
        ]);

        return \DB::transaction(function () use ($validated) {
            // Generate sequential account number
            $lastUser = User::withTrashed()->orderBy('id', 'desc')->first();
            $nextAccountNumber = $lastUser ? str_pad($lastUser->id + 1, 6, '0', STR_PAD_LEFT) : '000001';

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'type' => $validated['type'],
                'company_name' => $validated['company_name'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'account_number' => $nextAccountNumber
            ]);

            // Assign role based on type
            $user->assignRole($validated['type']);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
        });
    }

    public function login(Request $request)
{
    $request->validate([
        'phone' => ['required', 'regex:/^09\d{7,9}$/'],
        'password' => 'required'
    ]);

    $phone = preg_replace('/^0/', '+95', $request->phone);
    $user = User::where('phone', $phone)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
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