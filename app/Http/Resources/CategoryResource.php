<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_mm' => $this->name_mm,
            'slug_en' => $this->slug_en,
            'slug_mm' => $this->slug_mm,
            'image' => $this->image ? $this->getImageUrl($this->image) : null,
            'commission_rate' => (float) $this->commission_rate,
            'products_count' => $this->products_count ?? 0,
            'children_count' => $this->children_count ?? 0,
        ];

        if ($this->relationLoaded('children') && $this->children->isNotEmpty()) {
            $data['children'] = CategoryResource::collection($this->children);
        }

        if (isset($this->total_products)) {
            $data['total_products'] = $this->total_products;
        }

        if ($this->relationLoaded('products') && $this->products->isNotEmpty()) {
            $data['products'] = $this->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name_en' => $product->name_en,
                    'name_mm' => $product->name_mm,
                    'slug' => $product->slug_en,
                    'image' => $product->images[0]['url'] ?? null,
                    'category_id' => $product->category_id,
                ];
            });
        }

        return $data;
    }

    protected function getImageUrl(?string $path): ?string
    {
        if (!$path)
            return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        return Storage::disk('public')->url($path);
    }
}