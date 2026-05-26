<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $productParam = $this->route('product'); // can be Product model OR raw value depending on binding
        $product = $productParam instanceof \App\Models\Product ? $productParam : null;

        // Seller-only: only the authenticated seller who owns the product can update.
        $allowed = $user?->hasRole('seller')
            && (int) ($product?->seller_id) === (int) $user->id;

        if (!$allowed) {
            Log::warning('variant_update_unauthorized', [
                'user_id' => $user?->id,
                'user_type' => $user?->type,
                'user_roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : null,
                'route_product_raw' => is_object($productParam) ? get_class($productParam) : $productParam,
                'resolved_product_id' => $product?->id,
                'resolved_product_seller_id' => $product?->seller_id,
                'route_variant_id' => optional($this->route('variant'))->id,
                'path' => $this->path(),
                'ip' => $this->ip(),
            ]);
        }

        return $allowed;
    }

    public function rules(): array
    {
        $variantId = optional($this->route('variant'))->id;

        return [
            'sku'           => ['nullable', 'string', 'max:100', "unique:product_variants,sku,{$variantId}"],
            'price'         => ['sometimes', 'numeric', 'min:0'],
            'quantity'      => ['sometimes', 'numeric', 'min:0'],
            'quantity_unit' => ['nullable', 'string', 'max:50'],
            'moq'           => ['nullable', 'integer', 'min:1'],
            'quantity_step' => ['nullable', 'integer', 'min:1'],
            'image'         => ['nullable', 'string', 'max:2048'],
            'position'      => ['nullable', 'integer', 'min:1'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}
