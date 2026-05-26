<?php
// app/Http/Resources/ProductListResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Lightweight resource used in product listing pages / search results.
 * Does NOT load variants or options to keep queries fast.
 */
class ProductListResource extends JsonResource
{
    private function formatPrimaryImage(): ?array
    {
        $img = collect($this->images)->firstWhere('is_primary', true)
            ?? collect($this->images)->first();

        if (!$img) return null;

        $url = $img['url'] ?? $img['path'] ?? '';
        if ($url && !str_starts_with($url, 'http')) {
            $url = Storage::disk('public')->url(ltrim($url, '/'));
        }

        return array_merge($img, ['url' => $url]);
    }

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name_en'        => $this->name_en,
            'name_mm'        => $this->name_mm,
            'slug_en'        => $this->slug_en,
            'product_type'   => $this->product_type,
            'price'          => $this->price,          // base / "From" price
            'quantity_unit'  => $this->quantity_unit,
            'moq'            => $this->effectiveMoq(),
            'quantity_step'  => $this->effectiveStep(),
            'is_on_sale'     => $this->is_on_sale,
            'discount_price' => $this->discount_price,
            'sale_badge'     => $this->sale_badge,

            // ── Computed sale fields ──────────────────────────────────────────
            // ProductCard.jsx relies on these three to render the badge and
            // strikethrough price without duplicating the date-window logic.
            'is_currently_on_sale'   => $this->isCurrentlyOnSale(),
            'selling_price'          => $this->getSellingPrice(),
            'effective_discount_pct' => $this->getEffectiveDiscountPercentage(),

            'average_rating' => $this->average_rating,
            'review_count'   => $this->review_count,
            'is_featured'    => $this->is_featured,
            'is_active'      => $this->is_active,
            'is_new'         => $this->is_new,
            'in_stock'       => $this->isInStock(),
            'has_variants'   => $this->hasVariants(),
            'wholesale_tiers' => $this->whenLoaded('wholesaleTiers', fn() =>
                $this->wholesaleTiers->map(fn($t) => [
                    'min_qty'        => $t->min_qty,
                    'price_per_unit' => (float) $t->price_per_unit,
                    'discount_pct'   => (float) $t->discount_pct,
                    'label'          => $t->label,
                ])->values()
            ),
            // Primary image only — with full storage URL
            'image'          => $this->formatPrimaryImage(),
            'seller'         => $this->whenLoaded('seller', fn() => [
                'id'         => $this->seller->id,
                'store_name' => $this->seller->sellerProfile?->store_name,
                'store_slug' => $this->seller->sellerProfile?->store_slug,
            ]),
            // BUG FIX: was returning only 'name', but seller dashboard reads name_en/name_mm.
            // Added category_id at top level for the category filter comparison.
            'category_id'    => $this->category_id,
            'category'       => $this->whenLoaded('category', fn() => [
                'id'      => $this->category->id,
                'name_en' => $this->category->name_en,
                'name_mm' => $this->category->name_mm,
                'name'    => $this->category->name_en ?? $this->category->name,
            ]),
        ];
    }
}
