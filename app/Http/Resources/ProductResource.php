<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full product detail resource — used on the product detail page.
 * Loads options, option values, and variants with their option combinations.
 */
class ProductResource extends JsonResource
{
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
            'price'            => $this->price,
            'lowest_price'     => $this->lowestVariantPrice(),
            'discount_price'   => $this->discount_price,
            'discount_type'    => $this->discount_type,
            'discount_percentage' => $this->discount_percentage,
            'compare_at_price' => $this->compare_at_price,
            'sale_badge'       => $this->sale_badge,
            'is_on_sale'       => $this->is_on_sale,
            'discount_start'   => $this->discount_start,
            'discount_end'     => $this->discount_end,

            // ── B2B ───────────────────────────────────────────────────────────
            'moq'              => $this->moq,
            'quantity_unit'    => $this->quantity_unit,
            'min_order_unit'   => $this->min_order_unit,
            'lead_time'        => $this->lead_time,
            'packaging_details' => $this->packaging_details,

            // ── Stock ─────────────────────────────────────────────────────────
            'in_stock'         => $this->isInStock(),
            'total_stock'      => $this->totalStock(),   // null for digital/service

            // ── Variant system ────────────────────────────────────────────────
            // `has_variants` tells the frontend to show the option picker UI
            'has_variants'     => $this->whenLoaded(
                'options',
                fn() =>
                $this->options->isNotEmpty()
            ),
            // Options drive the UI widget (color swatches, size buttons, etc.)
            'options'          => ProductOptionResource::collection(
                $this->whenLoaded('options')
            ),
            // All purchasable variant combinations with their prices/stock
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
                $this->product_type === 'digital',
                $this->file_type
            ),
            'file_size'        => $this->when(
                $this->product_type === 'digital',
                $this->file_size
            ),
            // file_url intentionally excluded from public resource (served via signed URL)

            // ── Stats ─────────────────────────────────────────────────────────
            'average_rating'   => $this->average_rating,
            'review_count'     => $this->review_count,
            'views'            => $this->views,
            'is_featured'      => $this->is_featured,
            'is_new'           => $this->is_new,

            // ── Relations ─────────────────────────────────────────────────────
            'seller'           => $this->whenLoaded('seller', fn() => [
                'id'                => $this->seller->id,
                'store_name'        => $this->seller->sellerProfile?->store_name,
                'store_slug'        => $this->seller->sellerProfile?->store_slug,
                'logo'              => $this->seller->sellerProfile?->logo,
                'average_rating'    => $this->seller->sellerProfile?->average_rating,
            ]),
            'category'         => $this->whenLoaded('category', fn() => [
                'id'    => $this->category->id,
                'name'  => $this->category->name,
                'slug'  => $this->category->slug ?? null,
            ]),

            // ── Timestamps ───────────────────────────────────────────────────
            'listed_at'        => $this->listed_at,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,

            // ── Seller-only fields (only included when seller views their own) ─
            'status'           => $this->when(
                request()->user()?->hasRole(['seller', 'admin']),
                $this->status
            ),
            'rejection_reason' => $this->when(
                request()->user()?->hasRole(['seller', 'admin']),
                $this->rejection_reason
            ),
        ];
    }
}
