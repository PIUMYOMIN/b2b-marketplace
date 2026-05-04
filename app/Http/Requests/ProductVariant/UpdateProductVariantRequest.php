<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        return $this->user()->hasRole('admin') ||
               ($this->user()->hasRole('seller') && (int) $product?->seller_id === (int) $this->user()->id);
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
            'image'         => ['nullable', 'string', 'max:2048'],
            'position'      => ['nullable', 'integer', 'min:1'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}