<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the commission rate for an order by checking rules
 * in priority order. The first matching rule wins.
 *
 * Priority (highest → lowest):
 *   1. Account level   — seller's tier (gold/silver/bronze)
 *   2. Business type   — seller's registered business type
 *   3. Category        — primary category of the order's first item
 *   4. Default         — platform-wide fallback (currently 5%)
 */
class CommissionRateResolver
{
    /**
     * Resolve the commission rate and return both the rate and
     * which rule matched (for audit logging on the commission record).
     *
     * @return array{rate: float, rule_type: string, rule_id: int|null}
     */
    public function resolve(Order $order): array
    {
        try {
            $seller = User::with('sellerProfile')->find($order->seller_id);

            // ── 1. Account-level (seller tier) ──────────────────────────
            $tierKey = $seller?->sellerProfile?->seller_tier;
            if ($tierKey) {
                $rule = CommissionRule::active()
                    ->where('type', 'account_level')
                    ->where('reference_id', $this->tierToId($tierKey))
                    ->first();
                if ($rule) {
                    return $this->result((float) $rule->rate, 'account_level', $rule->id);
                }
            }

            // ── 2. Business type ────────────────────────────────────────
            $businessTypeId = $seller?->sellerProfile?->business_type_id;
            if ($businessTypeId) {
                $rule = CommissionRule::active()
                    ->where('type', 'business_type')
                    ->where('reference_id', $businessTypeId)
                    ->first();
                if ($rule) {
                    return $this->result((float) $rule->rate, 'business_type', $rule->id);
                }
            }

            // ── 3. Category ─────────────────────────────────────────────
            // Load items if not already eager-loaded
            $items      = $order->relationLoaded('items') ? $order->items : $order->items()->with('product:id,category_id')->get();
            $categoryId = $items->first()?->product?->category_id;
            if ($categoryId) {
                $rule = CommissionRule::active()
                    ->where('type', 'category')
                    ->where('reference_id', $categoryId)
                    ->first();
                if ($rule) {
                    return $this->result((float) $rule->rate, 'category', $rule->id);
                }
            }

            // ── 4. Platform default ─────────────────────────────────────
            $rule = CommissionRule::active()
                ->where('type', 'default')
                ->first();
            if ($rule) {
                return $this->result((float) $rule->rate, 'default', $rule->id);
            }

            // Hard fallback — should never reach here if seeder ran
            Log::warning('CommissionRateResolver: no rule found, falling back to 0.05', [
                'order_id'  => $order->id,
                'seller_id' => $order->seller_id,
            ]);
            return $this->result(0.05, 'fallback', null);

        } catch (\Exception $e) {
            Log::error('CommissionRateResolver failed: ' . $e->getMessage(), [
                'order_id' => $order->id ?? null,
            ]);
            return $this->result(0.05, 'fallback', null);
        }
    }


    /**
     * Resolve using raw sellerId + item array — call this BEFORE the Order is saved
     * so we don't need to load relationships from a persisted Order.
     *
     * @param int   $sellerId
     * @param array $sellerItems  [['product' => Product, ...], ...]
     */
    public function resolveForSeller(int $sellerId, array $sellerItems): array
    {
        try {
            $seller = User::with('sellerProfile')->find($sellerId);

            // 1. Account-level (tier)
            $tierKey = $seller?->sellerProfile?->seller_tier ?? 'bronze';
            $rule = CommissionRule::active()
                ->where('type', 'account_level')
                ->where('reference_id', $this->tierToId($tierKey))
                ->first();
            if ($rule) return $this->result((float) $rule->rate, 'account_level', $rule->id);

            // 2. Business type
            $businessTypeId = $seller?->sellerProfile?->business_type_id;
            if ($businessTypeId) {
                $rule = CommissionRule::active()
                    ->where('type', 'business_type')
                    ->where('reference_id', $businessTypeId)
                    ->first();
                if ($rule) return $this->result((float) $rule->rate, 'business_type', $rule->id);
            }

            // 3. Category (first item's category)
            $categoryId = collect($sellerItems)->first()['product']?->category_id ?? null;
            if ($categoryId) {
                $rule = CommissionRule::active()
                    ->where('type', 'category')
                    ->where('reference_id', $categoryId)
                    ->first();
                if ($rule) return $this->result((float) $rule->rate, 'category', $rule->id);
            }

            // 4. Default
            $rule = CommissionRule::active()->where('type', 'default')->first();
            if ($rule) return $this->result((float) $rule->rate, 'default', $rule->id);

            Log::warning('CommissionRateResolver: no rule found', ['seller_id' => $sellerId]);
            return $this->result(0.05, 'fallback', null);

        } catch (\Exception $e) {
            Log::error('CommissionRateResolver::resolveForSeller failed: ' . $e->getMessage());
            return $this->result(0.05, 'fallback', null);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function result(float $rate, string $type, ?int $ruleId): array
    {
        return ['rate' => $rate, 'rule_type' => $type, 'rule_id' => $ruleId];
    }

    /**
     * Map tier name to a stable integer used as reference_id in commission_rules.
     * Using fixed IDs (not DB IDs) so the seeder is predictable.
     *
     * bronze = 1, silver = 2, gold = 3
     */
    private function tierToId(string $tier): int
    {
        return match ($tier) {
            'gold'   => 3,
            'silver' => 2,
            default  => 1, // bronze
        };
    }
}