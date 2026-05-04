<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductOption\StoreProductOptionRequest;
use App\Http\Resources\ProductOptionResource;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductOptionController extends Controller
{
    /**
     * GET /seller/products/{product}/options
     * List all options (with values) for a product.
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $options = $product->options()->with('values')->get();

        return response()->json([
            'success' => true,
            'data'    => ProductOptionResource::collection($options),
        ]);
    }

    /**
     * POST /seller/products/{product}/options
     *
     * Replaces all options and their values for a product.
     * This is intentionally a "replace all" operation — the seller submits
     * the full desired option set in one go, which is simpler and safer
     * than individual add/remove operations on a complex combination system.
     *
     * WARNING: If variants already exist, this will delete them too
     * (since the old option values they reference are being replaced).
     */
    public function store(StoreProductOptionRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        DB::transaction(function () use ($request, $product) {
            // Drop existing variants first (they reference old option values)
            $product->variants()->forceDelete();

            // Drop existing options (cascades to option values via FK)
            $product->options()->delete();

            foreach ($request->input('options') as $position => $optionData) {
                $option = $product->options()->create([
                    'name'        => $optionData['name'],
                    'type'        => $optionData['type'],
                    'position'    => $optionData['position'] ?? ($position + 1),
                    'is_required' => $optionData['is_required'] ?? true,
                ]);

                // Input-type options have no predefined values
                if ($option->type !== 'input' && !empty($optionData['values'])) {
                    foreach ($optionData['values'] as $valPos => $valueData) {
                        $option->values()->create([
                            'label'    => $valueData['label'],
                            'value'    => $valueData['value'],
                            'meta'     => $valueData['meta'] ?? null,
                            'position' => $valueData['position'] ?? ($valPos + 1),
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('messages.products.options_saved'),
            'data'    => ProductOptionResource::collection(
                $product->options()->with('values')->get()
            ),
        ]);
    }

    /**
     * DELETE /seller/products/{product}/options
     * Remove all options (and their values + variants) for a product.
     */
    public function destroyAll(Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        DB::transaction(function () use ($product) {
            $product->variants()->forceDelete();
            $product->options()->delete();
        });

        return response()->json([
            'success' => true,
            'message' => __('messages.products.options_cleared'),
        ]);
    }

    // -------------------------------------------------------------------------

    private function authorizeProduct(Product $product): void
    {
        $user = request()->user();
        if (!$user->hasRole('admin') && (int) $product->seller_id !== (int) $user->id) {
            abort(403, __('messages.products.unauthorized_update'));
        }
    }
}