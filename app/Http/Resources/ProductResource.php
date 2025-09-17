<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'quantity' => $this->quantity,
            'category_id' => $this->category_id,
            'seller_id' => $this->seller_id,
            'average_rating' => (float) $this->average_rating,
            'review_count' => $this->reviews_count,
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
                return [[
                    'url' => $images,
                    'angle' => 'default',
                    'is_primary' => true
                ]];
            }
        }

        $formattedImages = [];
        foreach ($images as $index => $image) {
            if (is_string($image)) {
                $formattedImages[] = [
                    'url' => $image,
                    'angle' => 'default',
                    'is_primary' => $index === 0
                ];
            } else {
                $formattedImages[] = [
                    'url' => $image['url'] ?? $image['path'] ?? '',
                    'angle' => $image['angle'] ?? 'default',
                    'is_primary' => $image['is_primary'] ?? ($index === 0)
                ];
            }
        }

        return $formattedImages;
    }
}