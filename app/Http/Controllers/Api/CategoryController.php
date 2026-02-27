<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
     * Store a newly created category (admin only).
     */
    public function store(Request $request)
    {
        // Authorize – only admin can create categories
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name_en' => 'required|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description_en' => 'nullable|string',
            'description_mm' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // file upload
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at')
            ],
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'name_en',
                'name_mm',
                'description_en',
                'description_mm',
                'commission_rate',
                'parent_id',
                'is_active'
            ]);

            // Generate slugs
            $data['slug_en'] = $this->generateUniqueSlug($request->name_en, 'slug_en');
            if ($request->filled('name_mm')) {
                $data['slug_mm'] = $this->generateUniqueSlug($request->name_mm, 'slug_mm');
            }

            // Set default values
            $data['commission_rate'] = $data['commission_rate'] ?? 10.00;
            $data['is_active'] = $data['is_active'] ?? true;
            $data['parent_id'] = $data['parent_id'] ?? null;

            // Handle image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('categories', 'public');
                $data['image'] = $path;
            }

            $category = Category::create($data);

            return response()->json([
                'success' => true,
                'data' => new CategoryResource($category),
                'message' => 'Category created successfully.'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Category creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category.'
            ], 500);
        }
    }

    /**
     * Update the specified category (admin only).
     */
    public function update(Request $request, $id)
    {
        $requestId = uniqid();
        \Log::info("=== UPDATE REQUEST [$requestId] ===");
        \Log::info("Method: " . $request->method());
        \Log::info("URL: " . $request->fullUrl());
        \Log::info("Headers: " . json_encode($request->headers->all()));
        \Log::info("All input: ", $request->all());
        \Log::info("Has name_en? " . ($request->has('name_en') ? 'YES' : 'NO'));
        \Log::info("Has description_en? " . ($request->has('description_en') ? 'YES' : 'NO'));
        \Log::info("Has commission_rate? " . ($request->has('commission_rate') ? 'YES' : 'NO'));
        \Log::info("Files: ", $_FILES);

        $category = Category::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name_en' => 'sometimes|required|string|max:255',
            'name_mm' => 'sometimes|nullable|string|max:255',
            'description_en' => 'sometimes|nullable|string',
            'description_mm' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,jpg,gif,webp|max:5120',
            'commission_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at')
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // name_en + slug
        if ($request->has('name_en')) {
            $category->name_en = $request->name_en;
            $category->slug_en = $this->generateUniqueSlug(
                $request->name_en,
                'slug_en',
                $category->id
            );
        }

        // name_mm + slug_mm
        if ($request->has('name_mm')) {
            $category->name_mm = $request->name_mm;
            if ($request->name_mm) {
                $category->slug_mm = $this->generateUniqueSlug(
                    $request->name_mm,
                    'slug_mm',
                    $category->id
                );
            } else {
                $category->slug_mm = null;
            }
        }

        // descriptions
        if ($request->has('description_en')) {
            $category->description_en = $request->description_en;
        }
        if ($request->has('description_mm')) {
            $category->description_mm = $request->description_mm;
        }

        // commission_rate
        if ($request->has('commission_rate')) {
            $category->commission_rate = $request->commission_rate;
        }

        // is_active
        if ($request->has('is_active')) {
            $category->is_active = (bool) $request->is_active;
        }

        // image
        if ($request->hasFile('image')) {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $path = $request->file('image')->store('categories', 'public');
            $category->image = $path;
        } elseif ($request->has('image') && $request->image === "") {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $category->image = null;
        }

        // save basic fields
        $category->save();

        // parent (NestedSet)
        if ($request->has('parent_id')) {
            if ($request->parent_id != $category->id) {
                if ($request->parent_id) {
                    $parent = Category::find($request->parent_id);
                    if ($parent) {
                        $category->appendToNode($parent)->save();
                    } else {
                        $category->makeRoot()->save();
                    }
                } else {
                    $category->makeRoot()->save(); // root
                }
            }
        }

        $category->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category),
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

    /**
     * Remove the specified category (admin only).
     */
    public function destroy(Category $category)
    {
        // Authorize – only admin can delete categories
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            // Check if category has products – prevent deletion if any
            if ($category->products()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category because it has associated products.'
                ], 422);
            }

            // Delete the image if exists
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Category deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category.'
            ], 500);
        }
    }

    /**
     * Generate a unique slug for a given name and column.
     *
     * @param string $name
     * @param string $column (slug_en or slug_mm)
     * @param int|null $ignoreId (for updates, ignore this category id)
     * @return string
     */
    private function generateUniqueSlug(string $name, string $column, ?int $ignoreId = null): string
    {
        // Generate base slug
        $baseSlug = preg_replace('/[^A-Za-z0-9\-]+/u', '-', $name);
        $baseSlug = trim($baseSlug, '-');

        // Fallback if slug becomes empty (important for Burmese text)
        if (empty($baseSlug)) {
            $baseSlug = Str::random(8);
        }

        // Get all existing slugs that start with base slug
        $query = Category::where($column, 'like', $baseSlug . '%');

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        // Ignore soft deleted records
        $query->whereNull('deleted_at');

        $existingSlugs = $query->pluck($column)->toArray();

        // If base slug not taken, return it
        if (!in_array($baseSlug, $existingSlugs)) {
            return $baseSlug;
        }

        // Extract numeric suffixes
        $numbers = [];

        foreach ($existingSlugs as $slug) {
            if (preg_match('/^' . preg_quote($baseSlug, '/') . '-(\d+)$/', $slug, $matches)) {
                $numbers[] = (int) $matches[1];
            }
        }

        $nextNumber = empty($numbers) ? 1 : max($numbers) + 1;

        return $baseSlug . '-' . $nextNumber;
    }
}
