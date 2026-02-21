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
        'slug_en',
        'name_mm',
        'slug_mm',
        'description_en',
        'description_mm',
        'image',
        'commission_rate',
        'is_active',
    ];

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