<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_mm',
        'description_en',
        'description_mm',
        'commission_rate',
        'parent_id',
        'is_active',
        'image',
        'slug_en',
        'slug_mm'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'commission_rate' => 'float',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug_en)) {
                $category->slug_en = static::generateUniqueSlug($category->name_en, 'slug_en');
            }

            if (empty($category->slug_mm) && $category->name_mm) {
                $category->slug_mm = static::generateUniqueSlug($category->name_mm, 'slug_mm');
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name_en')) {
                $category->slug_en = static::generateUniqueSlug($category->name_en, 'slug_en', $category->id);
            }

            if ($category->isDirty('name_mm')) {
                $category->slug_mm = static::generateUniqueSlug($category->name_mm, 'slug_mm', $category->id);
            }
        });
    }

    // Relations
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // Parent relation (for nested set)
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Children relation (for nested set)
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    // Scopes
    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithProductCount($query)
    {
        return $query->withCount('products');
    }

// Nested set helper methods
    public function isRoot()
    {
        return is_null($this->parent_id);
    }

    public function makeRoot()
    {
        $this->parent_id = null;
        return $this;
    }

    public function appendToNode(Category $parent)
    {
        $this->parent_id = $parent->id;
        return $this;
    }

    /**
     * Get all descendants of this category (children at all levels).
     * Uses recursive CTE query for efficiency.
     */
    public function descendants()
    {
        $ids = collect([$this->id]);
        
        // Get all children recursively
        $this->loadDescendants($this->id, $ids);
        
        return static::whereIn('id', $ids->filter(fn($id) => $id !== $this->id));
    }

    /**
     * Recursively load all descendant IDs.
     */
    protected function loadDescendants($parentId, &$ids)
    {
        $children = static::where('parent_id', $parentId)->pluck('id');
        
        foreach ($children as $childId) {
            if (!$ids->contains($childId)) {
                $ids->push($childId);
                $this->loadDescendants($childId, $ids);
            }
        }
    }

    /**
     * Get all descendant IDs as a collection (for use in queries).
     */
    public function getDescendantIds()
    {
        $ids = collect([$this->id]);
        $this->loadDescendants($this->id, $ids);
        return $ids;
    }

    protected static function generateUniqueSlug(string $name, string $column, ?int $ignoreId = null): string
    {
        $baseSlug = preg_replace('/[^A-Za-z0-9\-]+/u', '-', $name);
        $baseSlug = trim($baseSlug, '-');

        if (empty($baseSlug)) {
            $baseSlug = Str::random(8);
        }

        $query = static::where($column, 'like', $baseSlug . '%');

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->whereNull('deleted_at');

        $existingSlugs = $query->pluck($column)->toArray();

        if (!in_array($baseSlug, $existingSlugs)) {
            return $baseSlug;
        }

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
