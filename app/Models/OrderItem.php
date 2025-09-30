<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'price',
        'quantity',
        'subtotal',
        'product_data'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'product_data' => 'array'
    ];

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Accessors
     */
    public function getFormattedPriceAttribute()
    {
        return 'MMK ' . number_format($this->price, 0);
    }

    public function getFormattedSubtotalAttribute()
    {
        return 'MMK ' . number_format($this->subtotal, 0);
    }

    /**
     * Calculate subtotal for this item
     */
    public function calculateSubtotal()
    {
        $this->subtotal = $this->price * $this->quantity;
        return $this->subtotal;
    }
}