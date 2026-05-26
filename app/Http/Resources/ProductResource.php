<?php
// app/Http/Resources/ProductResource.php
// CHANGE: add the four computed discount fields to the Pricing section

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ReviewResource;
use Illuminate\Support\Facades\Storage;

/**
 * Full product detail resource — used on the product detail page.
 * Loads options, option values, and variants with their option combinations.
 */
class ProductResource extends JsonResource
{
    /**
     * Normalise an images array so every URL is absolute.
     * ProductListResource does the same for its primary-image helper.
     * Keeping this in ProductResource avoids raw relative paths reaching
     * the frontend on the detail page.
     */
    private function normalizeImages(): array
    {
        $images = $this->images ?? [];
        if (is_string($images)) {
            try { $images = json_decode($images, true) ?? []; } catch (\Throwable) { $images = []; }
        }

        return array_map(function ($img) {
            $url = $img['url'] ?? $img['path'] ?? '';
            if ($url && !str_starts_with($url, 'http')) {
                $url = Storage::disk('public')->url(ltrim($url, '/'));
            }
            return array_merge($img, ['url' => $url]);
        }, $images);
    }

    public function toArray($request): array
    {
        return [
            // ── Identity ──────────────────────────────────────────────────────
            'id'               => $this->id,
            'name_en'          => $this->name_en,
            'name_mm'          => $this->name_mm,
            'slug_en'          => $this->slug_en,
            'slug_mm'          => $this->slug_mm,
            'description_en'   => $this->description_en,
            'description_mm'   => $this->description_mm,
            'product_type'     => $this->product_type,
            'sku'              => $this->sku,
            'barcode'          => $this->barcode,
            'brand'            => $this->brand,
            'model'            => $this->model,
            'material'         => $this->material,
            'origin'           => $this->origin,
            'condition'        => $this->condition,

            // ── Pricing ───────────────────────────────────────────────────────
            'price'                    => $this->price,           // base / "From" price
            'lowest_price'             => $this->lowestVariantPrice(),
            'discount_price'           => $this->discount_price,
            'discount_type'            => $this->discount_type,
            'discount_percentage'      => $this->discount_percentage,
            'compare_at_price'         => $this->compare_at_price,
            'sale_badge'               => $this->sale_badge,
            'is_on_sale'               => $this->is_on_sale,
            'discount_start'           => $this->discount_start,
            'discount_end'             => $this->discount_end,

            // ── Computed sale fields (used directly by frontend) ──────────────
            // These mirror what CartController computes per cart-item so that
            // ProductDetail.jsx, ProductCard.jsx, and Checkout.jsx all share
            // the same source of truth without duplicating the date-window logic.
            'is_currently_on_sale'     => $this->isCurrentlyOnSale(),        // bool
            'selling_price'            => $this->getSellingPrice(),           // effective buyer price
            'discount_saved'           => $this->getDiscountSaved(),          // MMK saved
            'effective_discount_pct'   => $this->getEffectiveDiscountPercentage(), // % for badge

            // ── B2B ───────────────────────────────────────────────────────────
            'moq'               => $this->effectiveMoq(),
            'quantity_step'     => $this->effectiveStep(),
            'quantity_unit'     => $this->quantity_unit,
            'min_order_unit'    => $this->min_order_unit,
            'lead_time'         => $this->lead_time,
            'packaging_details' => $this->packaging_details,

            // Wholesale pricing tiers — sorted ascending by min_qty.
            // Frontend uses this to render the tiered pricing table on ProductDetail.
            // Empty array = no wholesale tiers (retail-only product).
            'wholesale_tiers'   => $this->whenLoaded(
                'wholesaleTiers',
                fn() => $this->wholesaleTiers
                    ->where('is_active', true)
                    ->sortBy('min_qty')
                    ->values()
                    ->map(fn($t) => [
                        'min_qty'        => $t->min_qty,
                        'price_per_unit' => (float) $t->price_per_unit,
                        'discount_pct'   => (float) $t->discount_pct,
                        'label'          => $t->label,
                    ]),
                []  // default: empty array when not loaded (lightweight list views)
            ),

            // ── Stock ─────────────────────────────────────────────────────────
            'in_stock'         => $this->isInStock(),
            'total_stock'      => $this->totalStock(),

            // ── Variant system ────────────────────────────────────────────────
            'has_variants'     => $this->whenLoaded(
                'options',
                fn() => $this->options->isNotEmpty()
            ),
            'options'          => ProductOptionResource::collection(
                $this->whenLoaded('options')
            ),
            'variants'         => ProductVariantResource::collection(
                $this->whenLoaded('activeVariants')
            ),

            // ── Media ─────────────────────────────────────────────────────────
            'images'           => $this->images,
            'dimensions'       => $this->dimensions,
            'specifications'   => $this->specifications,

            // ── Shipping & Warranty ───────────────────────────────────────────
            'weight_kg'        => $this->weight_kg,
            'warranty'         => $this->warranty,
            'warranty_type'    => $this->warranty_type,
            'warranty_period'  => $this->warranty_period,
            'warranty_conditions' => $this->warranty_conditions,
            'return_policy'    => $this->return_policy,
            'return_conditions' => $this->return_conditions,
            'shipping_details' => $this->shipping_details,
            'shipping_cost'    => $this->shipping_cost,
            'shipping_time'    => $this->shipping_time,
            'shipping_origin'  => $this->shipping_origin,
            'hs_code'          => $this->hs_code,

            // ── Digital product fields ────────────────────────────────────────
            'file_type'        => $this->when(
                $this->product_type === 'digital', $this->file_type
            ),
            'file_size'        => $this->when(
                $this->product_type === 'digital', $this->file_size
            ),

            // ── Stats ─────────────────────────────────────────────────────────
            'average_rating'   => $this->average_rating,
            'review_count'     => $this->review_count,
            'views'            => $this->views,
            'is_featured'      => $this->is_featured,
            'is_new'           => $this->is_new,
            // Optional: included by ProductController@showPublic for product detail page
            'reviews'          => ReviewResource::collection($this->whenLoaded('reviews')),

            // ── Relations ─────────────────────────────────────────────────────
            'seller'           => $this->whenLoaded('seller', fn() => [
                'id'             => $this->seller->id,
                'name'           => $this->seller->name,
                'store_name'     => $this->seller->sellerProfile?->store_name,
                'store_slug'     => $this->seller->sellerProfile?->store_slug,
                'logo'           => $this->seller->sellerProfile?->logo,
                'average_rating' => $this->seller->sellerProfile?->average_rating,
            ]),
            'category'         => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug ?? null,
            ]),

            // ── Timestamps ────────────────────────────────────────────────────
            'listed_at'        => $this->listed_at,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,

            // ── Seller-only fields ─────────────────────────────────────────────
            'status'           => $this->when(
                $request->user()?->hasRole(['seller', 'admin']),
                $this->status
            ),
            'rejection_reason' => $this->when(
                $request->user()?->hasRole(['seller', 'admin']),
                $this->rejection_reason
            ),
        ];
    }
}
