<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'commission_rate' => 'required|numeric|min:0|max:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        // Generate slug from name
        $slug = Str::slug($request->name);

        // Ensure slug is unique
        $count = Category::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug = "{$slug}-" . ($count + 1);
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        // Handle base64 image if sent as string
        if ($request->has('image') && is_string($request->image) && Str::startsWith($request->image, 'data:image')) {
            $imageData = $request->image;
            $imagePath = $this->storeBase64Image($imageData);
        }

        $categoryData = [
            'name' => $request->name,
            'name_mm' => $request->name_mm,
            'description' => $request->description,
            'commission_rate' => $request->commission_rate,
            'slug' => $slug,
            'image' => $imagePath
        ];

        $category = Category::create($categoryData);

        if ($request->parent_id) {
            $parent = Category::find($request->parent_id);
            $category->appendToNode($parent)->save();
        } else {
            $category->makeRoot()->save();
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
            'commission_rate' => 'sometimes|numeric|min:0|max:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $data = $request->only(['name', 'name_mm', 'description', 'commission_rate']);

        if ($request->has('name')) {
            $slug = Str::slug($request->name);
            $count = Category::where('slug', 'LIKE', "{$slug}%")
                             ->where('id', '!=', $category->id)
                             ->count();
            if ($count > 0) {
                $slug = "{$slug}-" . ($count + 1);
            }
            $data['slug'] = $slug;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        // Handle base64 image if sent as string
        if ($request->has('image') && is_string($request->image) && Str::startsWith($request->image, 'data:image')) {
            // Delete old image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $this->storeBase64Image($request->image);
        }

        // Handle image removal if image field is empty
        if ($request->has('image') && empty($request->image)) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = null;
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
            'data' => $category->fresh()
        ]);
    }

    public function destroy(Category $category)
    {
        // Delete associated image
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Store base64 encoded image
     */
    private function storeBase64Image($base64Image)
    {
        // Extract the image data from the base64 string
        list($type, $data) = explode(';', $base64Image);
        list(, $data) = explode(',', $data);
        
        // Decode the base64 data
        $imageData = base64_decode($data);
        
        // Generate a unique filename
        $extension = explode('/', $type)[1];
        $filename = 'categories/' . uniqid() . '.' . $extension;
        
        // Store the image
        Storage::disk('public')->put($filename, $imageData);
        
        return $filename;
    }
}