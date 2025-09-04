<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withDepth()->defaultOrder()->get()->toTree();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function show(Category $category)
    {
        return response()->json([
            'success' => true,
            'data' => $category->load('products')
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'commission_rate' => 'required|numeric|min:0|max:1'
        ]);

        // Generate slug from name
        $slug = \Str::slug($request->name);

        $category = Category::create(array_merge(
            $request->only([
                'name', 
                'name_mm', 
                'description', 
                'commission_rate'
                // 'parent_id' is intentionally excluded; parent is set below
            ]),
            ['slug' => $slug]
        ));

        if ($request->parent_id) {
            $parent = Category::find($request->parent_id);
            $category->appendToNode($parent)->save();
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_mm' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'parent_id' => 'sometimes|exists:categories,id',
            'commission_rate' => 'sometimes|numeric|min:0|max:1'
        ]);

        $data = $request->only(['name', 'name_mm', 'description', 'commission_rate']);

        if ($request->has('name')) {
            $data['slug'] = \Str::slug($request->name);
        }

        $category->update($data);

        if ($request->has('parent_id')) {
            if ($request->parent_id) {
                $parent = Category::find($request->parent_id);
                $category->appendToNode($parent)->save();
            } else {
                $category->makeRoot()->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}