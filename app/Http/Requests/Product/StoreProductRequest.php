<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seller-only: product ownership is tied to authenticated seller_id.
        return $this->user()?->hasRole('seller') === true;
    }

    public function rules(): array
    {
        return [
            // ── Core ──────────────────────────────────────────────────────────
            'name_en'          => ['required', 'string', 'max:255'],
            'name_mm'          => ['nullable', 'string', 'max:255'],
            'description_en'   => ['nullable', 'string'],
            'description_mm'   => ['nullable', 'string'],
            'product_type'     => ['required', 'in:physical,digital,service'],
            'category_id'      => ['required', 'integer', 'exists:categories,id'],

            // ── Pricing ───────────────────────────────────────────────────────
            'price'            => ['required', 'numeric', 'min:0'],

            // ── Identification ────────────────────────────────────────────────
            'sku'              => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'barcode'          => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'brand'            => ['nullable', 'string', 'max:100'],
            'model'            => ['nullable', 'string', 'max:100'],
            'material'         => ['nullable', 'string', 'max:100'],
            'origin'           => ['nullable', 'string', 'max:100'],
            'condition'        => ['nullable', 'in:new,used_like_new,used_good,used_fair'],

            // ── B2B ───────────────────────────────────────────────────────────
            'quantity_unit'    => ['nullable', 'string', 'max:50'],
            'moq'              => ['nullable', 'integer', 'min:1'],
            'quantity_step'    => ['nullable', 'integer', 'min:1'],
            'min_order_unit'   => ['nullable', 'string', 'max:50'],
            'lead_time'        => ['nullable', 'string', 'max:100'],
            'packaging_details' => ['nullable', 'string'],
            'additional_info'  => ['nullable', 'string'],

            // ── Physical ──────────────────────────────────────────────────────
            'weight_kg'        => ['nullable', 'numeric', 'min:0'],
            'warranty'         => ['nullable', 'string', 'max:255'],
            'warranty_type'    => ['nullable', 'string', 'max:100'],
            'warranty_period'  => ['nullable', 'string', 'max:100'],
            'warranty_conditions'   => ['nullable', 'string'],
            'return_policy'    => ['nullable', 'string', 'max:255'],
            'return_conditions' => ['nullable', 'string'],
            'shipping_details' => ['nullable', 'string'],
            'shipping_cost'    => ['nullable', 'numeric', 'min:0'],
            'shipping_time'    => ['nullable', 'string', 'max:100'],
            'shipping_origin'  => ['nullable', 'string', 'max:100'],
            'customs_info'     => ['nullable', 'string', 'max:255'],
            'hs_code'          => ['nullable', 'string', 'max:50'],

            // ── Digital ───────────────────────────────────────────────────────
            'file_url'         => ['nullable', 'required_if:product_type,digital', 'string', 'max:2048'],
            'file_type'        => ['nullable', 'string', 'max:50'],
            'file_size'        => ['nullable', 'integer', 'min:0'],

            // ── Media ─────────────────────────────────────────────────────────
            'images'           => ['nullable', 'array'],
            'images.*'         => ['array'],
            'images.*.url'     => ['nullable', 'string', 'max:2048'],
            'images.*.path'    => ['nullable', 'string', 'max:2048'],
            'images.*.angle'   => ['nullable', 'string', 'in:front,back,side,top,default'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.uploaded_at' => ['nullable', 'date'],
            'specifications'   => ['nullable', 'array'],
            'dimensions'       => ['nullable', 'array'],

            // ── Discount (optional at creation) ──────────────────────────────
            'discount_type'    => ['nullable', 'in:percentage,fixed,none'],
            'discount_price'   => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_start'   => ['nullable', 'date'],
            'discount_end'     => ['nullable', 'date', 'after_or_equal:discount_start'],
            'is_active'        => ['nullable', 'boolean'],
            'is_featured'      => ['nullable', 'boolean'],
            'is_new'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_type.in'           => 'Product type must be physical, digital, or service.',
            'file_url.required_if'      => 'A file URL is required for digital products.',
            'discount_end.after_or_equal' => 'Discount end date must be after or equal to the start date.',
        ];
    }
}
