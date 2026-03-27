<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    /**
     * Verify email
     */
    public function verify(Request $request, $id, $hash): JsonResponse
    {
        if (!URL::hasValidSignature($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification link.'
            ], 403);
        }

        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link.'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.'
        ]);
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email resent.'
        ]);
    }

    /**
     * Verify email using 6-digit code.
     * POST /email/verify-code
     * Body: { code: "123456" }
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => true, 'message' => 'Email already verified.']);
        }

        if (!$user->verificationCodeIsValid($request->code)) {
            // Subtle timing: wrong code has same response time as expired code
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired code. Please request a new one.',
            ], 422);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // Clear the used code
        $user->update(['verification_code' => null, 'verification_code_expires_at' => null]);

        Log::info('Email verified via code', ['user_id' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Email verified successfully.']);
    }

}
