<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected string $guard_name = 'sanctum';

   protected $fillable = [
    'name', 'email', 'password', 'phone', 'type', 'user_id', 'is_active', 'address', 'city', 'state'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
        return $this->hasMany(Order::class,'buyer_id');
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isInactive()
    {
        return $this->status === 'inactive';
    }

    public function isSuspended()
    {
        return $this->status === 'suspended';
    }
}