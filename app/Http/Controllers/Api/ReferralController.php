<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    /**
     * GET /referral/my-link — get or generate the authenticated user's referral code
     */
    public function myLink(Request $request)
    {
        $user = $request->user();

        if (!$user->ref_code) {
            $user->ref_code = $this->generateUniqueCode();
            $user->save();
        }

        $frontendUrl = rtrim(config('app.frontend_url', 'https://pyonea.com'), '/');

        return response()->json([
            'success'   => true,
            'data' => [
                'ref_code'    => $user->ref_code,
                'ref_url'     => "{$frontendUrl}/register?ref={$user->ref_code}",
                'referred_count' => User::where('referred_by', $user->id)->count(),
                'referred_users' => User::where('referred_by', $user->id)
                    ->select('name', 'type', 'created_at')
                    ->get(),
            ],
        ]);
    }

    /**
     * POST /referral/validate — check a ref code is valid (used on register page)
     */
    public function validate(Request $request)
    {
        $code = $request->input('ref_code');
        $referrer = User::where('ref_code', $code)->first();

        if (!$referrer) {
            return response()->json(['success' => false, 'message' => 'Invalid referral code.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referrer_name' => $referrer->name,
                'ref_code'      => $code,
            ],
        ]);
    }

    /**
     * GET /admin/referrals — all users who have referred others (admin only)
     */
    public function adminIndex(Request $request)
    {
        $referrers = User::whereNotNull('ref_code')
            ->withCount('referredUsers as referred_count')
            ->orderByDesc('referred_count')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $referrers]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('ref_code', $code)->exists());

        return $code;
    }
}