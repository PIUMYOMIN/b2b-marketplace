<?php

namespace App\Services;

use App\Http\Resources\ProductListResource;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CartRecommendationService
{
    private const CACHE_TTL_SECONDS = 300;
    private const PER_GROUP = 8;
    private const CANDIDATE_LIMIT = 48;

    /**
     * Ranked cart recommendations grouped for checkout upsell.
     *
     * @param  list<int>  $productIds
     * @return array{
     *     complete_order: array<int, mixed>,
     *     same_seller: array<int, mixed>,
     *     same_category: array<int, mixed>,
     *     popular: array<int, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function forProductIds(array $productIds): array
    {
        $productIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return $this->emptyPayload();
        }

        $cacheKey = 'cart_recommendations:v1:'.implode('-', $productIds);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($productIds) {
            return $this->build($productIds);
        });
    }

    /**
     * @param  list<int>  $productIds
     */
    private function build(array $productIds): array
    {
        $cartProducts = Product::query()
            ->with(['category', 'seller.sellerProfile'])
            ->whereIn('id', $productIds)
            ->get();

        if ($cartProducts->isEmpty()) {
            return $this->emptyPayload();
        }

        $anchor = $cartProducts->sortByDesc(fn (Product $product) => (float) $product->price)->first();
        $excludeIds = $cartProducts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sellerIds = $cartProducts->pluck('seller_id')->filter()->unique()->values()->all();
        $categoryIds = $cartProducts->pluck('category_id')->filter()->unique()->values()->all();
        $anchorPrice = (float) ($anchor?->price ?? 0);

        $coPurchaseIds = $this->coPurchasedProductIds($excludeIds);
        $usedIds = $excludeIds;

        $completeOrder = $this->loadRankedProducts($coPurchaseIds, $usedIds);
        if ($completeOrder->count() < 4 && $categoryIds !== []) {
            $complements = $this->queryCatalog($usedIds)
                ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
                ->when($anchorPrice > 0, fn ($query) => $query->where('price', '<', max($anchorPrice * 0.65, 1)))
                ->orderByDesc('is_featured')
                ->orderByDesc('sales')
                ->limit(self::CANDIDATE_LIMIT)
                ->get();
            $completeOrder = $this->mergeUnique($completeOrder, $complements, $usedIds);
        }

        $sameSeller = $sellerIds === []
            ? collect()
            : $this->queryCatalog($usedIds)
                ->whereIn('seller_id', $sellerIds)
                ->orderByDesc('is_featured')
                ->orderByDesc('sales')
                ->limit(self::CANDIDATE_LIMIT)
                ->get();
        $sameSeller = $this->takeUnused($sameSeller, $usedIds);

        $sameCategory = $categoryIds === []
            ? collect()
            : $this->queryCatalog($usedIds)
                ->whereIn('category_id', $categoryIds)
                ->orderByDesc('is_featured')
                ->orderByDesc('sales')
                ->limit(self::CANDIDATE_LIMIT)
                ->get();
        $sameCategory = $this->takeUnused($sameCategory, $usedIds);

        $popular = $this->takeUnused(
            $this->queryCatalog($usedIds)
                ->where('is_featured', true)
                ->orderByDesc('listed_at')
                ->limit(self::CANDIDATE_LIMIT)
                ->get(),
            $usedIds
        );
        if ($popular->count() < 4) {
            $popular = $this->mergeUnique(
                $popular,
                $this->queryCatalog($usedIds)
                    ->orderByDesc('sales')
                    ->orderByDesc('listed_at')
                    ->limit(self::CANDIDATE_LIMIT)
                    ->get(),
                $usedIds
            );
        }

        $sellerName = $anchor?->seller?->sellerProfile?->store_name
            ?: $anchor?->seller?->name;

        return [
            'complete_order' => $this->serialize($completeOrder),
            'same_seller' => $this->serialize($sameSeller),
            'same_category' => $this->serialize($sameCategory),
            'popular' => $this->serialize($popular),
            'meta' => [
                'anchor_product_id' => $anchor?->id,
                'anchor_name_en' => $anchor?->name_en,
                'anchor_name_mm' => $anchor?->name_mm,
                'seller_id' => $anchor?->seller_id,
                'seller_name' => $sellerName,
                'category_id' => $anchor?->category_id,
                'category_name_en' => $anchor?->category?->name_en,
                'category_name_mm' => $anchor?->category?->name_mm,
            ],
        ];
    }

    /**
     * @param  list<int>  $cartProductIds
     * @return list<int>
     */
    private function coPurchasedProductIds(array $cartProductIds): array
    {
        if ($cartProductIds === [] || ! Schema::hasTable('order_items') || ! Schema::hasTable('orders')) {
            return [];
        }

        try {
            return DB::table('order_items as oi')
                ->join('order_items as companion', function ($join) {
                    $join->on('companion.order_id', '=', 'oi.order_id')
                        ->whereColumn('companion.product_id', '!=', 'oi.product_id');
                })
                ->join('orders', 'orders.id', '=', 'oi.order_id')
                ->whereIn('oi.product_id', $cartProductIds)
                ->whereNotIn('companion.product_id', $cartProductIds)
                ->whereNotNull('companion.product_id')
                ->whereNotIn('orders.status', ['cancelled'])
                ->select('companion.product_id', DB::raw('COUNT(DISTINCT companion.order_id) as freq'))
                ->groupBy('companion.product_id')
                ->orderByDesc('freq')
                ->limit(24)
                ->pluck('companion.product_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $excludeIds
     */
    private function queryCatalog(array $excludeIds)
    {
        return Product::query()
            ->approved()
            ->with([
                'seller.sellerProfile',
                'category',
                'wholesaleTiers' => fn ($query) => $query->where('is_active', true)->orderBy('min_qty'),
            ])
            ->withListAggregates()
            ->whereNotIn('id', $excludeIds === [] ? [0] : $excludeIds);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $usedIds
     */
    private function loadRankedProducts(array $ids, array &$usedIds): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $products = $this->queryCatalog($usedIds)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product) => array_search((int) $product->id, $ids, true));

        return $this->takeUnused($products, $usedIds);
    }

    private function mergeUnique(Collection $primary, Collection $extra, array &$usedIds): Collection
    {
        $merged = $primary->values();

        foreach ($extra as $product) {
            if ($merged->count() >= self::PER_GROUP) {
                break;
            }
            $id = (int) $product->id;
            if (in_array($id, $usedIds, true)) {
                continue;
            }
            $usedIds[] = $id;
            $merged->push($product);
        }

        return $merged->take(self::PER_GROUP)->values();
    }

    private function takeUnused(Collection $products, array &$usedIds): Collection
    {
        $picked = collect();

        foreach ($products as $product) {
            if ($picked->count() >= self::PER_GROUP) {
                break;
            }
            $id = (int) $product->id;
            if (in_array($id, $usedIds, true)) {
                continue;
            }
            $usedIds[] = $id;
            $picked->push($product);
        }

        return $picked->values();
    }

    private function serialize(Collection $products): array
    {
        return ProductListResource::collection($products->values())->resolve();
    }

    private function emptyPayload(): array
    {
        return [
            'complete_order' => [],
            'same_seller' => [],
            'same_category' => [],
            'popular' => [],
            'meta' => [
                'anchor_product_id' => null,
                'anchor_name_en' => null,
                'anchor_name_mm' => null,
                'seller_id' => null,
                'seller_name' => null,
                'category_id' => null,
                'category_name_en' => null,
                'category_name_mm' => null,
            ],
        ];
    }
}
