<?php

namespace App\Models;

use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Model;
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
        return $this->hasMany(Product::class);
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
}