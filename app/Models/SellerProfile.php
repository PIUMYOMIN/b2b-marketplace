<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellerProfile extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING_SETUP = 'pending_setup'; // New status for onboarding
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CLOSED = 'closed';


    const BUSINESS_TYPE_INDIVIDUAL = 'individual';
    const BUSINESS_TYPE_COMPANY = 'company';
    const BUSINESS_TYPE_RETAIL = 'retail';
    const BUSINESS_TYPE_WHOLESALE = 'wholesale';
    const BUSINESS_TYPE_PARTNERSHIP = 'partnership';
    const BUSINESS_TYPE_PRIVATE_LIMITED = 'private_limited';
    const BUSINESS_TYPE_PUBLIC_LIMITED = 'public_limited';
    const BUSINESS_TYPE_COOPERATIVE = 'cooperative';
    const BUSINESS_TYPE_MANUFACTURER = 'manufacturer';


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

    protected $casts = [
        'business_type' => 'string',
    ];

    protected $attributes = [
        'status' => 'pending',
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
     * Generate store slug
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
    // All required fields must be filled with actual data
    return !empty($this->store_name) && 
           !empty($this->business_type) && 
           !empty($this->contact_email) &&
           !empty($this->contact_phone) &&
           !empty($this->address) && 
           !empty($this->city) &&
           !empty($this->state) &&
           !empty($this->country);
}

/**
 * Get current onboarding step
 */
public function getOnboardingStep()
{
    if (empty($this->store_name) || empty($this->business_type)) {
        return 'store-basic';
    }
    
    if (empty($this->business_registration_number) && empty($this->tax_id) && empty($this->website)) {
        return 'business-details';
    }
    
    if (empty($this->address) || empty($this->city) || empty($this->state)) {
        return 'address';
    }
    
    return 'complete';
}

}