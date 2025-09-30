<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price',
        'product_data'
    ];

    protected $casts = [
        'product_data' => 'array',
        'price' => 'decimal:2'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getSubtotalAttribute()
    {
        return $this->price * $this->quantity;
    }

    // Check if product is still available
    public function getIsAvailableAttribute()
    {
        return $this->product && $this->product->is_active && $this->product->quantity > 0;
    }

    // Check if quantity is within stock limits
    public function getIsQuantityValidAttribute()
    {
        return $this->product && $this->quantity <= $this->product->quantity;
    }
}