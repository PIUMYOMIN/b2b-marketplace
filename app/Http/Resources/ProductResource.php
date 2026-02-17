<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en ?? $this->name_en,
            'slug_en' => $this->slug_en ?? $this->slug_en,
            'name_mm' => $this->name_mm,
            'slug_mm' => $this->slug_mm,
            'description' => $this->description_en ?? $this->description,
            'description_en' => $this->description_en,
            'description_mm' => $this->description_mm,
            'price' => (float) $this->price,
            'quantity' => $this->quantity,
            'category_id' => $this->category_id,
            'seller_id' => $this->seller_id,
            'average_rating' => (float) $this->average_rating,
            'review_count' => $this->review_count ?? $this->reviews_count ?? 0,
            'specifications' => $this->specifications,
            'images' => $this->formatImages($this->images),
            'weight_kg' => $this->weight_kg,
            'dimensions' => $this->dimensions,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'material' => $this->material,
            'origin' => $this->origin,
            'discount_price' => $this->discount_price,
            'discount_start' => $this->discount_start,
            'discount_end' => $this->discount_end,
            'views' => $this->views,
            'sales' => $this->sales,
            'is_featured' => $this->is_featured,
            'is_new' => $this->is_new,
            'is_on_sale' => $this->is_on_sale,
            'warranty' => $this->warranty,
            'warranty_type' => $this->warranty_type,
            'warranty_period' => $this->warranty_period,
            'warranty_conditions' => $this->warranty_conditions,
            'return_policy' => $this->return_policy,
            'return_conditions' => $this->return_conditions,
            'shipping_details' => $this->shipping_details,
            'shipping_cost' => $this->shipping_cost,
            'shipping_time' => $this->shipping_time,
            'shipping_origin' => $this->shipping_origin,
            'customs_info' => $this->customs_info,
            'hs_code' => $this->hs_code,
            'min_order_unit' => $this->min_order_unit,
            'moq' => $this->moq,
            'lead_time' => $this->lead_time,
            'packaging_details' => $this->packaging_details,
            'additional_info' => $this->additional_info,
            'listed_at' => $this->listed_at,
            'approved_at' => $this->approved_at,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category'),
            'seller' => $this->whenLoaded('seller'),
        ];
    }

    protected function formatImages($images)
    {
        if (empty($images)) {
            return [];
        }

        if (is_string($images)) {
            try {
                $images = json_decode($images, true);
            } catch (\Exception $e) {
                $url = $images;
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $url = Storage::disk('public')->url($url);
                }
                return [
                    [
                        'path' => $images,
                        'url' => $url,
                        'angle' => 'default',
                        'is_primary' => true
                    ]
                ];
            }
        }

        $formattedImages = [];
        foreach ($images as $index => $image) {
            if (is_string($image)) {
                $url = $image;
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $url = Storage::disk('public')->url($url);
                }
                $formattedImages[] = [
                    'path' => $image,
                    'url' => $url,
                    'angle' => 'default',
                    'is_primary' => $index === 0
                ];
            } else {
                $path = $image['path'] ?? $image['url'] ?? '';
                $url = $image['url'] ?? $path;

                if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
                    $url = Storage::disk('public')->url($url);
                }

                if (!$path && filter_var($image['url'] ?? '', FILTER_VALIDATE_URL)) {
                    $path = str_replace(Storage::disk('public')->url(''), '', $image['url']);
                }

                $formattedImages[] = [
                    'path' => $path,
                    'url' => $url,
                    'angle' => $image['angle'] ?? 'default',
                    'is_primary' => $image['is_primary'] ?? ($index === 0)
                ];
            }
        }

        return $formattedImages;
    }
}