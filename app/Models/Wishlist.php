<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wishlists';

    /**
     * Get the user that owns the wishlist item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that belongs to the wishlist item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Check if a product is in user's wishlist
     */
    public static function isInWishlist($userId, $productId): bool
    {
        return static::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->exists();
    }

    /**
     * Get wishlist items for a user with product details
     */
    public static function getUserWishlist($userId)
    {
        return static::with(['product' => function($query) {
            $query->select('id', 'name', 'name_mm', 'price', 'images', 'quantity', 'seller_id');
        }])
        ->where('user_id', $userId)
        ->get();
    }
}