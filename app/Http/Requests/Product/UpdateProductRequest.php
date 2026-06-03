<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $slugOrId = $this->route('slugOrId');
        $product = Product::where('slug_en', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->first();

        if (!$user || !$product) {
            return false;
        }

        return $user->hasRole('admin')
            || ($user->hasRole('seller') && (int) $product->seller_id === (int) $user->id);
    }

    public function rules(): array
    {
        $slugOrId  = $this->route('slugOrId');
        $productId = Product::where('slug_en', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->value('id');

        return [
            'name_en'          => ['sometimes', 'string', 'max:255'],
            'name_mm'          => ['nullable', 'string', 'max:255'],
            'description_en'   => ['nullable', 'string'],
            'description_mm'   => ['nullable', 'string'],
            'product_type'     => ['sometimes', 'in:physical,digital,service'],
            'category_id'      => ['sometimes', 'integer', 'exists:categories,id'],
            'price'            => ['sometimes', 'numeric', 'min:0'],
            'quantity'         => ['nullable', 'numeric', 'min:0'],
            'sku'              => ['nullable', 'string', 'max:100', "unique:products,sku,{$productId}"],
            'barcode'          => ['nullable', 'string', 'max:100', "unique:products,barcode,{$productId}"],
            'brand'            => ['nullable', 'string', 'max:100'],
            'model'            => ['nullable', 'string', 'max:100'],
            'material'         => ['nullable', 'string', 'max:100'],
            'origin'           => ['nullable', 'string', 'max:100'],
            'condition'        => ['nullable', 'in:new,used_like_new,used_good,used_fair'],
            'quantity_unit'    => ['nullable', 'string', 'max:50'],
            'moq'              => ['nullable', 'integer', 'min:1'],
            'quantity_step'    => ['nullable', 'integer', 'min:1'],
            'min_order_unit'   => ['nullable', 'string', 'max:50'],
            'lead_time'        => ['nullable', 'string', 'max:100'],
            'packaging_details' => ['nullable', 'string'],
            'additional_info'  => ['nullable', 'string'],
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
            'hs_code'          => ['nullable', 'string', 'max:50'],
            'file_url'         => ['nullable', 'string', 'max:2048'],
            'file_type'        => ['nullable', 'string', 'max:50'],
            'file_size'        => ['nullable', 'integer', 'min:0'],
            'images'           => ['nullable', 'array'],
            'images.*'         => ['array'],
            'images.*.url'     => ['nullable', 'string', 'max:2048'],
            'images.*.path'    => ['nullable', 'string', 'max:2048'],
            'images.*.angle'   => ['nullable', 'string', 'in:front,back,side,top,default'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.uploaded_at' => ['nullable', 'date'],
            'specifications'   => ['nullable', 'array'],
            'dimensions'       => ['nullable', 'array'],
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
}
