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
            'name' => $this->name,
            'name_mm' => $this->name_mm,
            'description' => $this->description,
            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,
            'min_order' => (int) $this->min_order,
            'lead_time' => $this->lead_time,
            'is_active' => (bool) $this->is_active,
            'average_rating' => $this->average_rating ? (float) number_format($this->average_rating, 2) : 0,
            'review_count' => $this->reviews_count ?? 0,
            'specifications' => $this->specifications ?? [],
            'images' => $this->formatImages($this->images),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'seller' => new UserResource($this->whenLoaded('seller')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    protected function formatImages($images)
{
    if (empty($images)) {
        return [];
    }

    if (is_string($images)) {
        $images = json_decode($images, true);
    }

    $formattedImages = [];
    foreach ($images as $index => $image) {
        if (is_string($image)) {
            // Simple string URL
            $formattedImages[] = [
                'url' => Storage::disk('public')->exists($image) ? 
                        Storage::disk('public')->url($image) : $image,
                'angle' => 'default',
                'is_primary' => $index === 0
            ];
        } else {
            // Array with details
            $formattedImages[] = [
                'url' => Storage::disk('public')->exists($image['url'] ?? '') ? 
                        Storage::disk('public')->url($image['url']) : 
                        ($image['url'] ?? ''),
                'angle' => $image['angle'] ?? 'default',
                'is_primary' => $image['is_primary'] ?? ($index === 0)
            ];
        }
    }

    return $formattedImages;
}
}