<?php

namespace App\Http\Resources;

use App\Models\Discount;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        // ── Resolve the effective discount from BOTH sources ───────────────
        // Priority 1: product-level discount (discount_price / discount_percentage)
        // Priority 2: active Discount model campaign (seller/admin created)
        $effective = $this->resolveEffectiveDiscount();

        return [
            'id'                    => $this->id,
            'name_en'               => $this->name_en,
            'slug_en'               => $this->slug_en,
            'name_mm'               => $this->name_mm,
            'slug_mm'               => $this->slug_mm,
            'description'           => $this->description_en ?? $this->description,
            'description_en'        => $this->description_en,
            'description_mm'        => $this->description_mm,

            // ── Pricing ────────────────────────────────────────────────────
            'price'                 => (float) $this->price,
            'selling_price'         => (float) $effective['selling_price'],   // effective checkout price
            'is_currently_on_sale'  => $effective['on_sale'],
            'discount_percentage'   => (float) $effective['discount_pct'],   // 0.0 when no discount
            'discount_price'        => $effective['discount_price'],          // raw fixed-price value or null
            'discount_saved'        => (float) $effective['discount_saved'],  // MMK saved per unit
            'discount_source'       => $effective['source'],                  // 'product' | 'campaign' | null
            'discount_label'        => $effective['label'],                   // e.g. "-15%" or null
            'discount_start'        => $this->discount_start,
            'discount_end'          => $this->discount_end,

            // ── Inventory ──────────────────────────────────────────────────
            'quantity'              => $this->quantity,
            'category_id'           => $this->category_id,
            'seller_id'             => $this->seller_id,

            // ── Ratings ────────────────────────────────────────────────────
            'average_rating'        => (float) ($this->average_rating ?? 0),
            'review_count'          => $this->reviews_count ?? $this->review_count ?? 0,

            // ── Product details ────────────────────────────────────────────
            'specifications'        => $this->specifications,
            'images'                => $this->formatImages($this->images),
            'weight_kg'             => $this->weight_kg,
            'dimensions'            => $this->dimensions,
            'sku'                   => $this->sku,
            'barcode'               => $this->barcode,
            'brand'                 => $this->brand,
            'model'                 => $this->model,
            'color'                 => $this->color,
            'material'              => $this->material,
            'origin'                => $this->origin,
            'views'                 => $this->views,
            'sales'                 => $this->sales,
            'is_featured'           => $this->is_featured,
            'is_new'                => $this->is_new,
            'is_on_sale'            => (bool) $this->is_on_sale,
            'is_active'             => $this->is_active,
            'warranty'              => $this->warranty,
            'warranty_type'         => $this->warranty_type,
            'warranty_period'       => $this->warranty_period,
            'warranty_conditions'   => $this->warranty_conditions,
            'return_policy'         => $this->return_policy,
            'return_conditions'     => $this->return_conditions,
            'shipping_details'      => $this->shipping_details,
            'shipping_cost'         => $this->shipping_cost,
            'shipping_time'         => $this->shipping_time,
            'shipping_origin'       => $this->shipping_origin,
            'customs_info'          => $this->customs_info,
            'hs_code'               => $this->hs_code,
            'min_order_unit'        => $this->min_order_unit,
            'moq'                   => $this->moq,
            'min_order'             => $this->moq,
            'lead_time'             => $this->lead_time,
            'packaging_details'     => $this->packaging_details,
            'additional_info'       => $this->additional_info,
            'listed_at'             => $this->listed_at,
            'approved_at'           => $this->approved_at,
            'status'                => $this->status,

            // ── Relations (whenLoaded) ─────────────────────────────────────
            'category' => $this->whenLoaded('category'),
            'seller'   => $this->whenLoaded('seller', function () {
                $profile = $this->seller->sellerProfile;
                return [
                    'id'         => $this->seller->id,
                    'store_name' => $profile?->store_name ?? $this->seller->name,
                    'logo'       => $profile?->store_logo
                        ? Storage::disk('public')->url($profile->store_logo)
                        : null,
                    'slug'       => $profile?->slug ?? null,
                    'seller_profile' => $profile ? [
                        'store_name' => $profile->store_name,
                        'store_slug' => $profile->store_slug,
                        'store_logo' => $profile->store_logo
                            ? Storage::disk('public')->url($profile->store_logo)
                            : null,
                    ] : null,
                ];
            }),
        ];
    }

    // ── Effective discount resolver ────────────────────────────────────────────

    private function resolveEffectiveDiscount(): array
    {
        $empty = [
            'selling_price' => (float) $this->price,
            'discount_pct'  => 0.0,
            'discount_price'=> null,
            'discount_saved'=> 0.0,
            'on_sale'       => false,
            'source'        => null,
            'label'         => null,
        ];

        if ((float) $this->price <= 0) {
            return $empty;
        }

        // ── Priority 1: product-level discount ────────────────────────────
        if ($this->is_currently_on_sale) {
            $sellingPrice = (float) $this->selling_price;
            $saved        = (float) $this->price - $sellingPrice;
            $pct          = (float) $this->discount_percentage;
            return [
                'selling_price' => $sellingPrice,
                'discount_pct'  => $pct,
                'discount_price'=> $this->discount_price ? (float) $this->discount_price : null,
                'discount_saved'=> round($saved, 2),
                'on_sale'       => true,
                'source'        => 'product',
                'label'         => $pct > 0 ? '-' . round($pct) . '%' : null,
            ];
        }

        // ── Priority 2: active campaign Discount model ────────────────────
        try {
            $now       = now();
            $productId  = $this->id;
            $categoryId = $this->category_id;
            $sellerId   = $this->seller_id;

            // Load only active, in-window discounts — single query, evaluated in PHP
            $campaignDiscount = Discount::where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                })
                ->get()
                ->first(fn($d) => $d->isApplicableToProduct($productId, $categoryId, $sellerId));

            if ($campaignDiscount) {
                $discountAmt  = (float) $campaignDiscount->calculateDiscount((float) $this->price);
                $sellingPrice = max(0, (float) $this->price - $discountAmt);
                $saved        = (float) $this->price - $sellingPrice;
                $pct          = round(($saved / (float) $this->price) * 100, 2);

                return [
                    'selling_price' => $sellingPrice,
                    'discount_pct'  => $pct,
                    'discount_price'=> $sellingPrice,
                    'discount_saved'=> round($saved, 2),
                    'on_sale'       => true,
                    'source'        => 'campaign',
                    'label'         => '-' . round($pct) . '%',
                ];
            }
        } catch (\Exception $e) {
            // Silently skip if Discount table doesn't exist yet (migration not run)
            \Log::debug('ProductResource discount lookup failed: ' . $e->getMessage());
        }

        return $empty;
    }

    // ── Image formatter ────────────────────────────────────────────────────────

    protected function formatImages($images): array
    {
        if (empty($images)) return [];

        if (is_string($images)) {
            try {
                $images = json_decode($images, true);
            } catch (\Exception $e) {
                $url = filter_var($images, FILTER_VALIDATE_URL)
                    ? $images
                    : Storage::disk('public')->url($images);
                return [['path' => $images, 'url' => $url, 'angle' => 'default', 'is_primary' => true]];
            }
        }

        if (!is_array($images)) return [];

        return collect($images)->values()->map(function ($image, $index) {
            if (is_string($image)) {
                $url = filter_var($image, FILTER_VALIDATE_URL)
                    ? $image
                    : Storage::disk('public')->url($image);
                return ['path' => $image, 'url' => $url, 'angle' => 'default', 'is_primary' => $index === 0];
            }

            $path = $image['path'] ?? $image['url'] ?? '';
            $url  = $image['url'] ?? $path;

            if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
                $url = Storage::disk('public')->url($url);
            }

            return [
                'path'       => $path,
                'url'        => $url,
                'angle'      => $image['angle'] ?? 'default',
                'is_primary' => $image['is_primary'] ?? ($index === 0),
            ];
        })->all();
    }
}
