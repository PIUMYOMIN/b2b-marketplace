<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with product counts
     */
    public function index(Request $request)
    {
        $query = Category::with(['children']);

        // Include product counts if requested
        if ($request->has('include') && str_contains($request->include, 'products_count')) {
            $query->withCount(['products as products_count']);
        }

        // Filter only parent categories if needed
        if ($request->has('parent_only')) {
            $query->whereNull('parent_id');
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $categories = $query->paginate($perPage);

        // If we need to add product counts for children too
        if ($request->has('include') && str_contains($request->include, 'products_count')) {
            foreach ($categories as $category) {
                if ($category->children) {
                    foreach ($category->children as $child) {
                        $child->products_count = Product::where('category_id', $child->id)
                            ->where('is_active', true)
                            ->count();
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ]
        ]);
    }

    /**
     * Get categories with detailed product counts (including active products)
     */
    public function indexWithProductCounts(Request $request)
    {
        $query = Category::whereNull('parent_id')
            ->with([
                'children' => function ($query) {
                    $query->withCount([
                        'products as products_count' => function ($q) {
                            $q->where('is_active', true);
                        }
                    ]);
                }
            ])
            ->withCount([
                'products as products_count' => function ($q) {
                    $q->where('is_active', true);
                }
            ]);

        // Filter categories with products only if requested
        if ($request->get('with_products_only')) {
            $query->whereHas('products', function ($q) {
                $q->where('is_active', true);
            });
        }

        $categories = $query->get();

        // Calculate total products including children
        foreach ($categories as $category) {
            $totalProducts = $category->products_count;

            if ($category->children) {
                foreach ($category->children as $child) {
                    $totalProducts += $child->products_count ?? 0;
                }
            }

            $category->total_products = $totalProducts;
        }

        return response()->json([
            'success' => true,
            'data' => $categories,
            'meta' => [
                'count' => $categories->count(),
                'total_products' => $categories->sum('total_products')
            ]
        ]);
    }
}
