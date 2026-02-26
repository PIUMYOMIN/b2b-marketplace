<?php

namespace App\Models;

use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use NodeTrait, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_mm',
        'description_en',
        'description_mm',
        'commission_rate',
        'parent_id',
        'is_active',
        'image',
        'slug_en'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'commission_rate' => 'float',
    ];

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

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Relations
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // Optional: parent category
    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Scopes
    public function scopeRootCategories($query)
    {
        return $query->whereIsRoot();
    }

    public function scopeWithProductCount($query)
    {
        return $query->withCount('products');
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