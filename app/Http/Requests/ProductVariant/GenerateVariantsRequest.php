<?php

namespace App\Http\Requests\ProductVariant;

use App\Models\Product;
use App\Support\ProductAccess;
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
        return $product instanceof Product && ProductAccess::canManage($this->user(), $product);
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
