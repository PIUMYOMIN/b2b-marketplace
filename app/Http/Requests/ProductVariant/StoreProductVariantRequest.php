<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * For manually creating a single variant.
 */
class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        return $this->user()?->hasRole('seller') === true
            && (int) ($product?->seller_id) === (int) $this->user()->id;
    }

    public function rules(): array
    {
        return [
            // option_value_ids must all belong to options of the parent product
            'option_value_ids' => ['required', 'array', 'min:1'],
            'option_value_ids.*' => ['integer', 'exists:product_option_values,id'],
            'sku'              => ['nullable', 'string', 'max:100', 'unique:product_variants,sku'],
            'price'            => ['required', 'numeric', 'min:0'],
            'quantity'         => ['required', 'numeric', 'min:0'],
            'quantity_unit'    => ['nullable', 'string', 'max:50'],
            'moq'              => ['nullable', 'integer', 'min:1'],
            'quantity_step'    => ['nullable', 'integer', 'min:1'],
            'image'            => ['nullable', 'string', 'max:2048'],
            'position'         => ['nullable', 'integer', 'min:1'],
            'is_active'        => ['nullable', 'boolean'],
        ];
    }
}
