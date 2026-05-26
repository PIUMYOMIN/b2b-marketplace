<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * For auto-generating all variant combinations from a product's options.
 * Seller sends default price/quantity applied to every generated variant.
 * They can then update individual variants after generation.
 */
class GenerateVariantsRequest extends FormRequest
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
            'price'         => ['required', 'numeric', 'min:0'],
            'quantity'      => ['nullable', 'numeric', 'min:0'],
            'quantity_unit' => ['nullable', 'string', 'max:50'],
            'moq'           => ['nullable', 'integer', 'min:1'],
            'quantity_step' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
