<?php

namespace App\Models;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerReview;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SellerProfile extends Model
{
    use HasFactory, SoftDeletes;
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CLOSED = 'closed';

    const BUSINESS_TYPE_RETAIL = 'retail';
    const BUSINESS_TYPE_WHOLESALE = 'wholesale';
    const BUSINESS_TYPE_SERVICE = 'service';
    const BUSINESS_TYPE_INDIVIDUAL = 'individual';
    const BUSINESS_TYPE_COMPANY = 'company';

    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'store_id',
        'business_type',
        'business_registration_number',
        'certificate',
        'tax_id',
        'description',
        'contact_email',
        'contact_phone',
        'website',
        'account_number',
        'social_facebook',
        'social_twitter',
        'social_instagram',
        'social_linkedin',
        'social_youtube',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'store_logo',
        'store_banner',
        'location',
        'status',
        'admin_notes'
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];
    
    protected $casts = [
        'business_type' => 'string',
    ];

    public static function getBusinessTypes()
    {
        return [
            self::BUSINESS_TYPE_INDIVIDUAL => 'Individual/Sole Proprietorship',
            self::BUSINESS_TYPE_PARTNERSHIP => 'Partnership',
            self::BUSINESS_TYPE_PRIVATE_LIMITED => 'Private Limited Company',
            self::BUSINESS_TYPE_PUBLIC_LIMITED => 'Public Limited Company',
            self::BUSINESS_TYPE_COOPERATIVE => 'Cooperative',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviews()
    {
        return $this->hasMany(SellerReview::class, 'seller_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id', 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    public function isClosed()
    {
        return $this->status === 'closed';
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }

    public function totalReviews()
    {
        return $this->reviews()->count();
    }

    public function topProducts($limit = 5)
    {
        return $this->products()
                    ->withCount('orders')
                    ->orderBy('orders_count', 'desc')
                    ->limit($limit)
                    ->get();
    }

    public function getRouteKeyName()
    {
        return 'store_slug';
    }

    /**
     * Generate store ID
     */
    public static function generateStoreId()
    {
        $prefix = 'STORE';
        $timestamp = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        return "{$prefix}{$timestamp}{$random}";
    }

    /**
     * Generate store slug // this function is duplicated, consider removing one
     */
    public static function generateStoreSlug($storeName)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $storeName)));
        $originalSlug = $slug;
        $counter = 1;

        while (self::where('store_slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
 * Check if onboarding is complete
 */
public function isOnboardingComplete()
{
    // All required fields must be filled with actual data (not empty strings)
    return !empty(trim($this->store_name)) && 
           !empty(trim($this->business_type)) && 
           !empty(trim($this->contact_email)) &&
           !empty(trim($this->contact_phone)) &&
           !empty(trim($this->address)) && 
           !empty(trim($this->city)) &&
           !empty(trim($this->state)) &&
           !empty(trim($this->country));
}

/**
 * Get current onboarding step
 */
public function getOnboardingStep()
{
    // Check if store-basic fields are empty
    if (empty(trim($this->store_name)) || empty(trim($this->business_type))) {
        return 'store-basic';
    }
    
    // Check if business details are mostly empty (optional fields)
    if (empty(trim($this->business_registration_number)) && 
        empty(trim($this->tax_id)) && 
        empty(trim($this->website))) {
        return 'business-details';
    }
    
    // Check if address fields are empty
    if (empty(trim($this->address)) || empty(trim($this->city)) || empty(trim($this->state))) {
        return 'address';
    }
    
    return 'complete';
}

public static function generateUniqueSlug($storeName)
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $storeName)));
    $originalSlug = $slug;
    $counter = 1;

    while (self::where('store_slug', $slug)->exists()) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

}