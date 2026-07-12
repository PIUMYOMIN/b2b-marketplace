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
        'deletion_requested_at',
        'status',
        'address',
        'city',
        'township',
        'state',
        'country',
        'postal_code',
        'profile_photo',
        'date_of_birth',
        // Email verification code (OTP)
        'verification_code',
        'verification_code_expires_at',
        // Social / OAuth
        'social_id',
        'social_provider',
        // Identity documents
        'identity_document_front',
        'identity_document_back',
        'identity_document_type',
        // Notification preferences
'notification_preferences',
        'ref_code',
        'referred_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'verification_code_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'notification_preferences' => 'array',
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

    /**
     * Cascade soft-deletes to owned records.
     *
     * The DB-level onDelete('cascade') on seller_profiles.user_id only fires
     * on a hard (physical) DELETE.  Because this model uses SoftDeletes,
     * calling $user->delete() only sets deleted_at — the DB cascade never
     * fires, leaving the seller_profile (and its products) as orphaned rows
     * still visible in admin panels and on public store URLs.
     *
     * We fix this here once so every code path — UserController, console
     * commands, tests — gets the cascade for free.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            // Cascade soft-delete to the seller profile and its products.
            if ($sellerProfile = $user->sellerProfile) {
                // Soft-delete every product listed under this store so they
                // disappear from the marketplace immediately.
                $sellerProfile->products()->each(fn ($product) => $product->delete());

                // Soft-delete the profile itself.
                $sellerProfile->delete();
            }
        });
    }

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
        return $this->hasMany(ProductReview::class);
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

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isInactive(): bool
    {
        return $this->is_active === false;
    }

    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    public function hasPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    public function deletionScheduledAt(): ?\Illuminate\Support\Carbon
    {
        if (!$this->deletion_requested_at) {
            return null;
        }

        $graceDays = (int) config('app.account_deletion_grace_days', 30);

        return $this->deletion_requested_at->copy()->addDays($graceDays);
    }

    public function isDeletionGraceExpired(): bool
    {
        $scheduledAt = $this->deletionScheduledAt();

        return $scheduledAt !== null && $scheduledAt->isPast();
    }

    public function canRecoverFromPendingDeletion(): bool
    {
        return $this->hasPendingDeletion() && !$this->isDeletionGraceExpired();
    }

    public function canAuthenticate(): bool
    {
        if ($this->status === 'suspended') {
            return false;
        }

        // Allow sign-in during the deletion grace period so the user can recover.
        if ($this->canRecoverFromPendingDeletion()) {
            return true;
        }

        if ($this->status !== 'active') {
            return false;
        }

        return $this->isActive();
    }

    /**
     * Human-readable login denial when credentials are valid but sign-in is blocked.
     *
     * @return array{code: string, message: string}|null
     */
    public function authenticationDenial(): ?array
    {
        if ($this->canAuthenticate()) {
            return null;
        }

        if ($this->status === 'suspended') {
            return [
                'code' => 'account_suspended',
                'message' => __('messages.auth.account_suspended'),
            ];
        }

        if ($this->hasPendingDeletion() && $this->isDeletionGraceExpired()) {
            return [
                'code' => 'account_permanently_deleted',
                'message' => __('messages.users.account_permanently_deleted'),
            ];
        }

        if ($this->status !== 'active') {
            return [
                'code' => 'account_status_inactive',
                'message' => __('messages.auth.account_status_inactive'),
            ];
        }

        if (!$this->isActive()) {
            return [
                'code' => 'account_deactivated',
                'message' => __('messages.auth.account_deactivated'),
            ];
        }

        return [
            'code' => 'login_not_allowed',
            'message' => __('messages.auth.login_not_allowed'),
        ];
    }

    // COD commission invoice relationship (for enforcement logic)
    public function codInvoices()
    {
        return $this->hasMany(CodCommissionInvoice::class, 'seller_id');
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
        return $this->following()->firstOrCreate(
            ['seller_id' => $sellerId],
            ['user_id'   => $this->id]
        );
    }

    public function unfollow($sellerId)
    {
        return $this->following()->where('seller_id', $sellerId)->delete();
    }

    /**
     * Generate a fresh 6-digit verification code, store it, and return it.
     * Expires in 15 minutes.
     */
    public function generateVerificationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(15),
        ]);
        return $code;
    }

    public function verificationCodeIsValid(string $code): bool
    {
        return $this->verification_code === $code
            && $this->verification_code_expires_at
            && now()->lt($this->verification_code_expires_at);
    }

    /**
     * Referral relationships
     */
    public function referredUsers()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function pushTokens()
    {
        return $this->hasMany(PushToken::class);
    }

}