<?php

namespace App\Models;
use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use NodeTrait;

    protected $fillable = [
        'name_en',
        'name_mm',
        'description_en',
        'description_mm',
        'slug',
        'image',
        'commission_rate',
        'parent_id',
        'is_active',
    ];

    public function parent() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children() {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithProductCount($query)
    {
        return $query->withCount('products');
    }

    public function getFullPathAttribute()
    {
        $path = $this->name;
        $parent = $this->parent;
        while ($parent) {
            $path = $parent->name . ' > ' . $path;
            $parent = $parent->parent;
        }
        return $path;
    }

    public function products() {
        return $this->hasMany(Product::class);
    }
}