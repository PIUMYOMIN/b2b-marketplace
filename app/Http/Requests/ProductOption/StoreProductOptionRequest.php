<?php

namespace App\Http\Requests\ProductOption;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creating/replacing a full set of options for a product.
 *
 * Expected payload:
 * {
 *   "options": [
 *     {
 *       "name": "Color",
 *       "type": "color",
 *       "position": 1,
 *       "is_required": true,
 *       "values": [
 *         { "label": "Red",  "value": "red",  "position": 1, "meta": {"hex": "#EF4444"} },
 *         { "label": "Blue", "value": "blue", "position": 2, "meta": {"hex": "#3B82F6"} }
 *       ]
 *     },
 *     {
 *       "name": "Size",
 *       "type": "size",
 *       "position": 2,
 *       "is_required": true,
 *       "values": [
 *         { "label": "S", "value": "s", "position": 1 },
 *         { "label": "M", "value": "m", "position": 2 }
 *       ]
 *     }
 *   ]
 * }
 *
 * Note: options of type "input" should have an empty `values` array.
 */
class StoreProductOptionRequest extends FormRequest
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
            'options'                  => ['required', 'array', 'min:1'],
            'options.*.name'           => ['required', 'string', 'max:100'],
            'options.*.type'           => ['required', 'in:color,size,text,image,input'],
            'options.*.position'       => ['nullable', 'integer', 'min:1'],
            'options.*.is_required'    => ['nullable', 'boolean'],
            'options.*.values'         => ['nullable', 'array'],
            'options.*.values.*.label'    => ['required_unless:options.*.type,input', 'string', 'max:100'],
            'options.*.values.*.value'    => ['required_unless:options.*.type,input', 'string', 'max:100'],
            'options.*.values.*.position' => ['nullable', 'integer', 'min:1'],
            'options.*.values.*.meta'     => ['nullable', 'array'],
            'options.*.values.*.meta.hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'options.*.values.*.meta.hex.regex' => 'Hex colour must be a valid 6-digit hex code (e.g. #FF0000).',
        ];
    }
}