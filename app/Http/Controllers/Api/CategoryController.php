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
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->where('is_active', 1)
            ->get();

        foreach ($categories as $category) {

            // Get all child IDs including itself
            $categoryIds = $category->descendants()
                ->pluck('id')
                ->push($category->id);

            $category->products_count = \App\Models\Product::whereIn('category_id', $categoryIds)
                ->where('is_active', 1)
                ->where('status', 'approved')
                ->count();

            $category->children_count = $category->children->count();
        }

        return response()->json([
            'success' => true,
            'data' => $categories
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

    /**
     * Get categories with their active products
     */
    public function indexWithProducts(Request $request)
    {
        $categories = Category::whereNull('parent_id')
            ->withCount([
                'products as products_count' => function ($q) {
                    $q->where('is_active', true);
                }
            ])
            ->with([
                'products' => function ($q) {
                    $q->select('id', 'name_en', 'category_id') // IMPORTANT
                        ->where('is_active', true);
                }
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }


    /**
     * Display the specified category with its active products
     */
    public function show(Category $category)
    {
        $category->load([
            'children' => function ($query) {
                $query->with([
                    'products' => function ($q) {
                        $q->where('is_active', true);
                    }
                ]);
            },
            'products' => function ($q) {
                $q->where('is_active', true);
            }
        ]);
        $category->products_count = $category->products()->where('is_active', true)->count();
        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

}
