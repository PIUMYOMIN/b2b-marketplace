<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public listing of active seller coupons for the storefront "Local Deals" page.
 */
class LocalDealController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
            'region' => 'sometimes|string|in:all,yangon,mandalay,naypyidaw,ayeyarwady,shan,other',
        ]);

        $perPage = min((int) $request->input('per_page', 24), 50);

        $query = Coupon::query()
            ->active()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->whereHas('seller', function ($q) {
                $q->whereHas('sellerProfile', function ($q2) {
                    $q2->whereIn('status', [
                        SellerProfile::STATUS_APPROVED,
                        SellerProfile::STATUS_ACTIVE,
                    ])->whereNotNull('store_slug')
                        ->where('store_slug', '!=', '');
                });
            })
            ->with(['seller.sellerProfile:user_id,store_name,store_slug,state,city']);

        if ($search = $request->input('search')) {
            $term = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        $region = $request->input('region', 'all');
        $this->applyRegionFilter($query, $region);

        try {
            $coupons = $query->latest('id')->paginate($perPage);
        } catch (\Throwable $e) {
            Log::error('local-deals index: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to load local deals.',
            ], 500);
        }

        $data = $coupons->getCollection()->map(function (Coupon $coupon) {
            $profile = $coupon->seller?->sellerProfile;
            $state = $profile?->state ?? '';

            return [
                'id' => $coupon->id,
                'name' => $coupon->name,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => (string) $coupon->value,
                'min_order_amount' => $coupon->min_order_amount !== null
                    ? (string) $coupon->min_order_amount
                    : null,
                'expires_at' => $coupon->expires_at?->toIso8601String(),
                'starts_at' => $coupon->starts_at?->toIso8601String(),
                'region_key' => $this->deriveRegionKey($state),
                'seller' => [
                    'store_name' => $profile?->store_name ?? $coupon->seller?->name,
                    'store_slug' => $profile?->store_slug,
                    'state' => $profile?->state,
                    'city' => $profile?->city,
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Map free-text state/region from seller profile to storefront filter keys.
     */
    private function deriveRegionKey(?string $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        $s = mb_strtolower($state, 'UTF-8');

        $checks = [
            'yangon' => 'yangon',
            'mandalay' => 'mandalay',
            'naypyidaw' => 'naypyidaw',
            'nay pyi taw' => 'naypyidaw',
            'ayeyarwady' => 'ayeyarwady',
            'ayeyarwaddy' => 'ayeyarwady',
            'irrawaddy' => 'ayeyarwady',
            'shan' => 'shan',
        ];

        foreach ($checks as $needle => $key) {
            if (str_contains($s, $needle)) {
                return $key;
            }
        }

        return null;
    }

    private function applyRegionFilter($query, string $region): void
    {
        if ($region === '' || $region === 'all') {
            return;
        }

        if ($region === 'other') {
            $query->whereHas('seller.sellerProfile', function ($q) {
                $q->whereNotNull('state')
                    ->where('state', '!=', '')
                    ->where(function ($q2) {
                        $needles = ['yangon', 'mandalay', 'naypyidaw', 'nay pyi taw', 'ayeyarwady', 'ayeyarwaddy', 'irrawaddy', 'shan'];
                        foreach ($needles as $n) {
                            $q2->whereRaw('LOWER(state) NOT LIKE ?', ['%' . $n . '%']);
                        }
                    });
            });

            return;
        }

        $needleGroups = [
            'yangon' => ['yangon'],
            'mandalay' => ['mandalay'],
            'naypyidaw' => ['naypyidaw', 'nay pyi taw'],
            'ayeyarwady' => ['ayeyarwady', 'ayeyarwaddy', 'irrawaddy'],
            'shan' => ['shan'],
        ];

        if (! isset($needleGroups[$region])) {
            return;
        }

        $needles = $needleGroups[$region];
        $query->whereHas('seller.sellerProfile', function ($q) use ($needles) {
            $q->where(function ($q2) use ($needles) {
                foreach ($needles as $n) {
                    $q2->orWhereRaw('LOWER(state) LIKE ?', ['%' . $n . '%']);
                }
            });
        });
    }
}
