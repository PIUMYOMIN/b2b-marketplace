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
            'name_en' => 'required|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description_en' => 'nullable|string',
            'description_mm' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'commission_rate' => 'required|numeric|min:0|max:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        // Generate slugs from names
        $slugEn = Str::slug($request->name_en);
        $slugMm = $request->name_mm ? Str::slug($request->name_mm) : null;

        // Ensure slugs are unique
        $countEn = Category::where('slug_en', 'LIKE', "{$slugEn}%")->count();
        if ($countEn > 0) {
            $slugEn = "{$slugEn}-" . ($countEn + 1);
        }

        if ($slugMm) {
            $countMm = Category::where('slug_mm', 'LIKE', "{$slugMm}%")->count();
            if ($countMm > 0) {
                $slugMm = "{$slugMm}-" . ($countMm + 1);
            }
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
            'name_en' => $request->name_en,
            'slug_en' => $slugEn,
            'name_mm' => $request->name_mm,
            'slug_mm' => $slugMm,
            'description_en' => $request->description_en,
            'description_mm' => $request->description_mm,
            'commission_rate' => $request->commission_rate,
            'image' => $imagePath,
            'is_active' => $request->get('is_active', true),
        ];

        $category = Category::create($categoryData);

        // Handle parent-child relationship
        if ($request->parent_id) {
            $parent = Category::find($request->parent_id);
            if ($parent) {
                $category->appendToNode($parent)->save();
            }
        } else {
            $category->makeRoot()->save();
        }

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Category created successfully'
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name_en' => 'sometimes|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description_en' => 'nullable|string',
            'description_mm' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'commission_rate' => 'sometimes|numeric|min:0|max:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'is_active' => 'sometimes|boolean'
        ]);

        $data = [
            'name_en' => $request->get('name_en', $category->name_en),
            'name_mm' => $request->get('name_mm', $category->name_mm),
            'description_en' => $request->get('description_en', $category->description_en),
            'description_mm' => $request->get('description_mm', $category->description_mm),
            'commission_rate' => $request->get('commission_rate', $category->commission_rate),
            'is_active' => $request->get('is_active', $category->is_active)
        ];

        // Generate new slugs if names changed
        if ($request->has('name_en') && $request->name_en !== $category->name_en) {
            $newSlugEn = Str::slug($request->name_en);
            $count = Category::where('slug_en', 'LIKE', $newSlugEn . '%')
                ->where('id', '!=', $category->id)
                ->count();
            if ($count > 0) {
                $newSlugEn = $newSlugEn . '-' . ($count + 1);
            }
            $data['slug_en'] = $newSlugEn;
        }

        if ($request->has('name_mm') && $request->name_mm !== $category->name_mm) {
            $newSlugMm = $request->name_mm ? Str::slug($request->name_mm) : null;
            if ($newSlugMm) {
                $count = Category::where('slug_mm', 'LIKE', $newSlugMm . '%')
                    ->where('id', '!=', $category->id)
                    ->count();
                if ($count > 0) {
                    $newSlugMm = $newSlugMm . '-' . ($count + 1);
                }
            }
            $data['slug_mm'] = $newSlugMm;
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

        // Handle parent change
        if ($request->has('parent_id')) {
            if ($request->parent_id) {
                $parent = Category::find($request->parent_id);
                if ($parent && $parent->id !== $category->id) {
                    $category->appendToNode($parent)->save();
                }
            } else {
                // Make it a root category
                $category->makeRoot()->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Category updated successfully'
        ]);
    }

    public function destroy(Category $category)
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing products'
            ], 400);
        }

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
