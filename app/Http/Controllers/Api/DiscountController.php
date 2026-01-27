<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:percentage,fixed,free_shipping',
            'status' => 'sometimes|in:active,expired,upcoming',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Discount::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $now = now();
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)
                        ->where('starts_at', '<=', $now)
                        ->where('expires_at', '>=', $now);
                    break;
                case 'expired':
                    $query->where('expires_at', '<', $now);
                    break;
                case 'upcoming':
                    $query->where('starts_at', '>', $now);
                    break;
            }
        }

        // For sellers, show only their discounts
        if (Auth::user()->hasRole('seller')) {
            $query->where('created_by', Auth::id());
        }

        $discounts = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $discounts,
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:discounts,code',
            'type' => 'required|in:percentage,fixed,free_shipping',
            'value' => 'required_if:type,percentage,fixed|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'starts_at' => 'required|date|after_or_equal:today',
            'expires_at' => 'required|date|after:starts_at',
            'applicable_to' => 'required|in:all_products,specific_products,specific_categories,specific_sellers',
            'applicable_product_ids' => 'required_if:applicable_to,specific_products|array',
            'applicable_product_ids.*' => 'exists:products,id',
            'applicable_category_ids' => 'required_if:applicable_to,specific_categories|array',
            'applicable_category_ids.*' => 'exists:categories,id',
            'applicable_seller_ids' => 'required_if:applicable_to,specific_sellers|array',
            'applicable_seller_ids.*' => 'exists:users,id',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'is_one_time_use' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'name',
                'type',
                'value',
                'min_order_amount',
                'max_uses',
                'starts_at',
                'expires_at',
                'applicable_to',
                'max_uses_per_user',
                'is_one_time_use'
            ]);

            // Generate code if not provided
            if (!$request->has('code') || empty($request->code)) {
                $data['code'] = strtoupper(Str::random(8));
            } else {
                $data['code'] = strtoupper($request->code);
            }

            // Handle applicable IDs
            if ($request->has('applicable_product_ids')) {
                $data['applicable_product_ids'] = $request->applicable_product_ids;
            }

            if ($request->has('applicable_category_ids')) {
                $data['applicable_category_ids'] = $request->applicable_category_ids;
            }

            if ($request->has('applicable_seller_ids')) {
                $data['applicable_seller_ids'] = $request->applicable_seller_ids;
            }

            $data['created_by'] = Auth::id();
            $data['is_active'] = $request->get('is_active', true);

            $discount = Discount::create($data);

            return response()->json([
                'success' => true,
                'data' => $discount,
                'message' => 'Discount created successfully'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Discount creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create discount: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Discount $discount)
    {
        // Authorization check
        if (Auth::id() !== $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this discount'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:discounts,code,' . $discount->id,
            'type' => 'sometimes|in:percentage,fixed,free_shipping',
            'value' => 'sometimes|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'starts_at' => 'sometimes|date',
            'expires_at' => 'sometimes|date|after:starts_at',
            'applicable_to' => 'sometimes|in:all_products,specific_products,specific_categories,specific_sellers',
            'applicable_product_ids' => 'nullable|array',
            'applicable_product_ids.*' => 'exists:products,id',
            'applicable_category_ids' => 'nullable|array',
            'applicable_category_ids.*' => 'exists:categories,id',
            'applicable_seller_ids' => 'nullable|array',
            'applicable_seller_ids.*' => 'exists:users,id',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'is_one_time_use' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'name',
                'type',
                'value',
                'min_order_amount',
                'max_uses',
                'starts_at',
                'expires_at',
                'applicable_to',
                'max_uses_per_user',
                'is_one_time_use',
                'is_active'
            ]);

            if ($request->has('code')) {
                $updateData['code'] = strtoupper($request->code);
            }

            // Handle applicable IDs
            if ($request->has('applicable_product_ids')) {
                $updateData['applicable_product_ids'] = $request->applicable_product_ids;
            }

            if ($request->has('applicable_category_ids')) {
                $updateData['applicable_category_ids'] = $request->applicable_category_ids;
            }

            if ($request->has('applicable_seller_ids')) {
                $updateData['applicable_seller_ids'] = $request->applicable_seller_ids;
            }

            $discount->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $discount->fresh(),
                'message' => 'Discount updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update discount: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Discount $discount)
    {
        // Authorization check
        if (Auth::id() !== $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this discount'
            ], 403);
        }

        try {
            $discount->delete();

            return response()->json([
                'success' => true,
                'message' => 'Discount deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete discount: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validateDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'total_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $discount = Discount::where('code', strtoupper($request->code))
                ->where('is_active', true)
                ->first();

            if (!$discount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid discount code'
                ], 404);
            }

            // Check if discount is expired
            if (now()->lt($discount->starts_at) || now()->gt($discount->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discount code has expired'
                ], 400);
            }

            // Check max uses
            if ($discount->max_uses && $discount->used_count >= $discount->max_uses) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discount code has reached maximum uses'
                ], 400);
            }

            // Check min order amount
            if ($discount->min_order_amount && $request->total_amount < $discount->min_order_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order amount not met'
                ], 400);
            }

            // Check if user has already used this discount
            if ($discount->is_one_time_use && Auth::check()) {
                $used = \App\Models\DiscountUsage::where('discount_id', $discount->id)
                    ->where('user_id', Auth::id())
                    ->exists();

                if ($used) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already used this discount code'
                    ], 400);
                }
            }

            // Check applicability for each product
            $applicableProducts = [];
            $totalDiscount = 0;

            foreach ($request->product_ids as $productId) {
                $product = Product::find($productId);

                if ($discount->isApplicableToProduct($productId, $product->category_id, $product->seller_id)) {
                    $applicableProducts[] = $productId;
                    $totalDiscount += $discount->calculateDiscount($product->price);
                }
            }

            if (empty($applicableProducts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discount code not applicable to selected products'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'discount' => $discount,
                    'applicable_product_ids' => $applicableProducts,
                    'total_discount' => $totalDiscount,
                    'final_amount' => $request->total_amount - $totalDiscount
                ],
                'message' => 'Discount code applied successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate discount: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProductDiscounts($productId)
    {
        $product = Product::findOrFail($productId);

        $discounts = Discount::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where(function ($query) use ($product) {
                $query->where('applicable_to', 'all_products')
                    ->orWhere(function ($q) use ($product) {
                        $q->where('applicable_to', 'specific_products')
                            ->whereJsonContains('applicable_product_ids', $product->id);
                    })
                    ->orWhere(function ($q) use ($product) {
                        $q->where('applicable_to', 'specific_categories')
                            ->whereJsonContains('applicable_category_ids', $product->category_id);
                    })
                    ->orWhere(function ($q) use ($product) {
                        $q->where('applicable_to', 'specific_sellers')
                            ->whereJsonContains('applicable_seller_ids', $product->seller_id);
                    });
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $discounts
        ]);
    }

    public function toggleStatus(Discount $discount)
    {
        // Authorization check
        if (Auth::id() !== $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this discount'
            ], 403);
        }

        $discount->update(['is_active' => !$discount->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Discount status updated',
            'is_active' => $discount->is_active
        ]);
    }
}
