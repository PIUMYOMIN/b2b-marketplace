<?php

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
            'moq'            => $this->moq,
            'is_on_sale'              => $this->is_on_sale,
            'discount_price'          => $this->discount_price,
            'discount_percentage'     => $this->discount_percentage,
            'sale_badge'              => $this->sale_badge,
            // ── Computed sale fields (mirrors CartController logic) ────────────
            'is_currently_on_sale'    => $this->isCurrentlyOnSale(),
            'selling_price'           => $this->isCurrentlyOnSale() ? (float) $this->discount_price : (float) $this->price,
            'discount_saved'          => $this->isCurrentlyOnSale() ? round((float) $this->price - (float) $this->discount_price, 2) : 0,
            'average_rating' => $this->average_rating,
            'review_count'   => $this->review_count,
            'is_featured'    => $this->is_featured,
            'is_active'      => $this->is_active,
            'in_stock'       => $this->isInStock(),
            'has_variants'   => $this->hasVariants(),
            // Primary image only — with full storage URL
            'image'          => $this->formatPrimaryImage(),
            'seller'         => $this->whenLoaded('seller', fn() => [
                'id'         => $this->seller->id,
                'store_name' => $this->seller->sellerProfile?->store_name,
                'store_slug' => $this->seller->sellerProfile?->store_slug,
            ]),
            'category'       => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
        ];
    }
}