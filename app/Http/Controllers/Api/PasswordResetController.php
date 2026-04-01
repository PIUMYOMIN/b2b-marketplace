<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Notifications\ResetPassword;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to the given user (email or phone).
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string', // can be email or phone
            'recaptcha_token' => ['required', new Recaptcha],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.general.validation_failed'),
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->email;

        // Determine if it's email or phone
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            // It's an email
            $user = User::where('email', $identifier)->first();
        } else {
            // Assume it's a phone number, normalize it
            $normalizedPhone = $this->normalizeMyanmarPhone($identifier);
            if (!$this->isValidMyanmarPhone($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.auth.invalid_phone')
                ], 422);
            }
            $user = User::where('phone', $normalizedPhone)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('messages.auth.user_not_found')
            ], 404);
        }

        // Generate password reset token
        $token = Password::createToken($user);

        // Send custom notification
        $user->notify(new ResetPassword($token));

        return response()->json([
            'success' => true,
            'message' => __('messages.auth.verification_sent')
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|string', // email or phone from the reset link
            'password' => 'required|string|min:6|confirmed',
            'recaptcha_token' => ['required', new Recaptcha],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.general.validation_failed'),
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->email;

        // Find user by email or normalized phone
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $identifier)->first();
        } else {
            $normalizedPhone = $this->normalizeMyanmarPhone($identifier);
            if (!$this->isValidMyanmarPhone($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.auth.invalid_phone')
                ], 422);
            }
            $user = User::where('phone', $normalizedPhone)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('messages.auth.user_not_found')
            ], 404);
        }

        // Verify token using Laravel's password broker
        $broker = Password::broker();
        if (!$broker->tokenExists($user, $request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Delete used token
        $broker->deleteToken($user);

        // Fire password reset event
        event(new PasswordReset($user));

        return response()->json([
            'success' => true,
            'message' => __('messages.auth.logout_success')
        ]);
    }

    /**
     * Normalize Myanmar phone number to +959 format (copied from AuthController)
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
}