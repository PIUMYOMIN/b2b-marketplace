<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected string $guard_name = 'sanctum';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'type',
        'user_id',
        'is_active',
        'status',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'profile_photo',
        'date_of_birth',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'settings' => 'array',
            'preferences' => 'array', // FIX: was 'peferences' (typo)
            'password' => 'hashed',
        ];
    }

    // protected $attributes = [
    //     'settings' => '{
    //     "email_notifications": true,
    //     "order_notifications": true,
    //     "inventory_alerts": true,
    //     "review_notifications": true,
    //     "two_factor_auth": false,
    //     "login_notifications": true,
    //     "show_sold_out": true,
    //     "show_reviews": true,
    //     "show_inventory_count": false
    // }',
    //     'preferences' => '{}'
    // ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailApi());
    }

    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function sellerReviews()
    {
        return $this->hasMany(SellerReview::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function wishlist()
    {
        return $this->belongsToMany(Product::class, 'wishlists');
    }


    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isSeller()
    {
        return $this->hasRole('seller');
    }

    public function isBuyer()
    {
        return $this->hasRole('buyer');
    }

    // FIX: use is_active (boolean column) for active/inactive checks.
    // The status enum ('active','inactive','suspended') is for admin-level account state.
    // is_active is the operational on/off flag used throughout the app.
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function isActive()
    {
        return $this->is_active === true;
    }

    public function isInactive()
    {
        return $this->is_active === false;
    }

    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    // Follow relationships
    public function following()
    {
        return $this->hasMany(Follow::class, 'user_id');
    }

    public function followers()
    {
        return $this->hasMany(Follow::class, 'seller_id');
    }

    public function followingSellers()
    {
        return $this->belongsToMany(User::class, 'follows', 'user_id', 'seller_id')
            ->withTimestamps();
    }

    public function followerUsers()
    {
        return $this->belongsToMany(User::class, 'follows', 'seller_id', 'user_id')
            ->withTimestamps();
    }

    // Helper methods
    public function isFollowing($sellerId)
    {
        return $this->following()->where('seller_id', $sellerId)->exists();
    }

    public function follow($sellerId)
    {
        if (!$this->isFollowing($sellerId)) {
            return $this->following()->create(['seller_id' => $sellerId]);
        }
        return null;
    }

    public function unfollow($sellerId)
    {
        return $this->following()->where('seller_id', $sellerId)->delete();
    }
}
