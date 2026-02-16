<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with product counts.
     */
    public function index()
    {
        // Get only root categories (parent_id null) that are active
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->where('is_active', true)
            ->get();

        // Calculate product count including all descendants for each root category
        foreach ($categories as $category) {
            $categoryIds = $category->descendants()->pluck('id')->push($category->id);
            $category->products_count = Product::whereIn('category_id', $categoryIds)
                ->where('is_active', true)
                ->where('status', 'approved')
                ->count();

            $category->children_count = $category->children->count();
        }

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories)
        ]);
    }

    /**
     * Get categories with detailed product counts (including active products).
     * Optionally filter to only categories that have products.
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

        // Optional: only categories that have at least one active product
        if ($request->boolean('with_products_only')) {
            $query->whereHas('products', function ($q) {
                $q->where('is_active', true);
            });
        }

        $categories = $query->get();

        // Calculate total products including children
        foreach ($categories as $category) {
            $total = $category->products_count;
            foreach ($category->children as $child) {
                $total += $child->products_count ?? 0;
            }
            $category->total_products = $total;
        }

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'count' => $categories->count(),
                'total_products' => $categories->sum('total_products'),
            ],
        ]);
    }

    /**
     * Get categories with their active products (for category browser or sitemap).
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
                    $q->select('id', 'name_en', 'name_mm', 'slug_en', 'image', 'category_id')
                        ->where('is_active', true);
                }
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories)
        ]);
    }

    /**
     * Display the specified category with its active products and children.
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

        // Set a dynamic attribute for the resource
        $category->products_count = $category->products()->where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category)
        ]);
    }
}
