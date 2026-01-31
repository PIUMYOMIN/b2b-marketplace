<?php
namespace App\Models;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;

class SellerProfile extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    const STATUS_SETUP_PENDING = 'setup_pending';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ACTIVE = 'active';
    const STATUS_REJECTED = 'rejected';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CLOSED = 'closed';

    // Verification status constants
    const VERIFICATION_PENDING = 'pending';
    const VERIFICATION_UNDER_REVIEW = 'under_review';
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_REJECTED = 'rejected';

    // Verification level constants
    const LEVEL_UNVERIFIED = 'unverified';
    const LEVEL_BASIC = 'basic';
    const LEVEL_VERIFIED = 'verified';
    const LEVEL_PREMIUM = 'premium';

    // Document status constants
    const DOCUMENT_NOT_SUBMITTED = 'not_submitted';
    const DOCUMENT_PENDING = 'pending';
    const DOCUMENT_UNDER_REVIEW = 'under_review';
    const DOCUMENT_APPROVED = 'approved';
    const DOCUMENT_REJECTED = 'rejected';

    // Identity document types
    const ID_NATIONAL_ID = 'national_id';
    const ID_PASSPORT = 'passport';
    const ID_DRIVING_LICENSE = 'driving_license';
    const ID_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'store_id',
        'business_type_id',
        'business_type',
        'business_registration_number',
        'certificate',
        'tax_id',
        'store_description',
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
        'shipping_enabled',

        // Document fields
        'business_registration_document',
        'tax_registration_document',
        'identity_document_front',
        'identity_document_back',
        'additional_documents',
        'identity_document_type',

        // Status fields
        'status',
        'verification_status',
        'verification_level',
        'verified_by',
        'verified_at',
        'verification_notes',

        // Document submission
        'documents_submitted',
        'documents_submitted_at',

        // Badge system
        'badge_type',
        'badge_expires_at',

        // Onboarding
        'onboarding_status',
        'current_step',
        'onboarding_completed_at',

        // Document review
        'document_status',
        'document_rejection_reason',

        // Admin notes
        'admin_notes',

        'return_policy',
        'shipping_policy',
        'warranty_policy',
        'privacy_policy',
        'terms_of_service',
        'commission_rate',
        'auto_withdrawal',
        'withdrawal_threshold',
        'preferred_payment_method',
        'is_active',
        'vacation_mode',
        'vacation_message',
        'vacation_start_date',
        'vacation_end_date',
        'currency',
        'business_hours_enabled',
        'business_hours'
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'additional_documents' => 'array',
        'verified_at' => 'datetime',
        'documents_submitted_at' => 'datetime',
        'badge_expires_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'documents_submitted' => 'boolean',
        'shipping_enabled' => 'boolean',
        'is_active' => 'boolean',
        'vacation_mode' => 'boolean',
        'auto_withdrawal' => 'boolean',
        'business_hours' => 'array',
        'vacation_start_date' => 'date',
        'vacation_end_date' => 'date'
    ];

    const STATUS_FLOW = [
        'setup_pending' => 'pending',
        'pending' => 'under_review',
        'under_review' => 'approved',
        'approved' => 'active',
        'rejected' => 'setup_pending',
    ];

    public function getNextStep()
    {
        return self::STATUS_FLOW[$this->status] ?? 'setup_pending';
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
     * Generate store ID
     */
    public static function generateStoreId()
    {
        $prefix = 'STORE';
        $timestamp = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}{$timestamp}{$random}";
    }

    public function getOnboardingProgressAttribute($value)
    {
        $progress = json_decode($value, true) ?? [
            'current_step' => 'store-basic',
            'completed_steps' => [],
            'progress_percentage' => 0,
        ];

        // Auto-calculate progress if missing
        if ($progress['progress_percentage'] === 0) {
            $steps = ['store-basic', 'business-details', 'address', 'documents', 'review'];
            $completed = array_filter($steps, function ($step) use ($progress) {
                return in_array($step, $progress['completed_steps'] ?? []);
            });

            $progress['progress_percentage'] = (count($completed) / count($steps)) * 100;
        }

        return $progress;
    }

    public function markStepComplete($step)
    {
        $progress = $this->onboarding_progress;
        $progress['current_step'] = $step;
        $progress['completed_steps'][] = $step;
        $progress['completed_steps'] = array_unique($progress['completed_steps']);

        $this->onboarding_progress = json_encode($progress);
        $this->save();
    }

    /**
     * Check if profile is verified
     */
    public function isVerified()
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED &&
            in_array($this->verification_level, [self::LEVEL_VERIFIED, self::LEVEL_PREMIUM]);
    }

    /**
     * Check if seller has verification badge
     */
    public function hasVerificationBadge()
    {
        return $this->isVerified() &&
            !empty($this->badge_type) &&
            (!$this->badge_expires_at || $this->badge_expires_at->isFuture());
    }

    /**
     * Get verification badge details
     */
    public function getVerificationBadge()
    {
        if (!$this->hasVerificationBadge()) {
            return null;
        }

        $badges = [
            'verified' => [
                'name' => 'Verified Seller',
                'color' => 'bg-green-100 text-green-800 border-green-300',
                'icon' => '✓',
            ],
            'premium' => [
                'name' => 'Premium Seller',
                'color' => 'bg-purple-100 text-purple-800 border-purple-300',
                'icon' => '★',
            ],
            'featured' => [
                'name' => 'Featured Store',
                'color' => 'bg-blue-100 text-blue-800 border-blue-300',
                'icon' => '✩',
            ],
            'top_rated' => [
                'name' => 'Top Rated',
                'color' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                'icon' => '♥',
            ],
        ];

        return $badges[$this->badge_type] ?? $badges['verified'];
    }

    /**
     * Check if profile has all required information
     */
    public function hasCompleteProfile()
    {
        $requiredFields = [
            'store_name',
            'business_type_id',
            'contact_email',
            'contact_phone',
            'address',
            'city',
            'state',
            'country',
        ];

        foreach ($requiredFields as $field) {
            if (empty(trim($this->$field))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if required documents are submitted based on business type
     */
    public function hasRequiredDocuments()
    {
        if ($this->business_type === 'individual') {
            // For individuals, require identity document
            return !empty($this->identity_document_front);
        } else {
            // For businesses, require business registration and tax documents
            return !empty($this->business_registration_document) &&
                !empty($this->tax_registration_document);
        }
    }

    /**
     * Get missing documents based on business type
     */
    public function getMissingDocuments()
    {
        $missing = [];

        if ($this->business_type === 'individual') {
            if (empty($this->identity_document_front)) {
                $missing[] = 'Identity Document (Front)';
            }
        } else {
            if (empty($this->business_registration_document)) {
                $missing[] = 'Business Registration Document';
            }
            if (empty($this->tax_registration_document)) {
                $missing[] = 'Tax Registration Document';
            }
        }

        return $missing;
    }

    /**
     * Get current onboarding step
     */
    public function getOnboardingStep()
    {
        // Check if basic info is complete
        if (!$this->store_name || !$this->business_type) {
            return 'store-basic';
        }

        // Check if business details are complete
        if (!$this->business_registration_number || !$this->tax_id) {
            return 'business-details';
        }

        // Check if address is complete
        if (!$this->address || !$this->city || !$this->state) {
            return 'address';
        }

        // Check if documents are submitted
        if (!$this->hasRequiredDocuments()) {
            return 'documents';
        }

        return 'submit';
    }

    /**
     * Get onboarding completion status
     */
    public function getOnboardingProgress()
    {
        $completedSteps = 0;
        $totalSteps = 5; // Basic, Business, Address, Documents, Submit

        // Check store basic info
        if (!empty(trim($this->store_name)) && !empty(trim($this->business_type))) {
            $completedSteps++;
        }

        // Check business details
        if (!empty(trim($this->contact_email)) && !empty(trim($this->contact_phone))) {
            $completedSteps++;
        }

        // Check address
        if (!empty(trim($this->address)) && !empty(trim($this->city)) && !empty(trim($this->state))) {
            $completedSteps++;
        }

        // Check documents
        if ($this->hasRequiredDocuments()) {
            $completedSteps++;
        }

        // Check submission
        if ($this->documents_submitted) {
            $completedSteps++;
        }

        return [
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'percentage' => ($completedSteps / $totalSteps) * 100,
            'next_step' => $this->getOnboardingStep(),
        ];
    }

    /**
     * Check if onboarding can be completed
     */
    public function canCompleteOnboarding()
    {
        return $this->hasCompleteProfile() &&
            $this->hasRequiredDocuments() &&
            !$this->documents_submitted;
    }

    /**
     * Mark documents as submitted
     */
    public function markDocumentsSubmitted()
    {
        $this->update([
            'documents_submitted' => true,
            'documents_submitted_at' => now(),
            'document_status' => self::DOCUMENT_PENDING,
            'verification_status' => self::VERIFICATION_UNDER_REVIEW,
        ]);
    }

    /**
     * Mark onboarding as complete
     */
    public function markOnboardingComplete()
    {
        $this->update([
            'onboarding_completed_at' => now(),
            'status' => self::STATUS_PENDING,
            'verification_status' => self::VERIFICATION_PENDING,
        ]);
    }

    /**
     * Verify seller
     */
    public function verify($verifierId, $level = self::LEVEL_VERIFIED, $badgeType = 'verified', $notes = null)
    {
        $this->update([
            'verified_at' => now(),
            'verified_by' => $verifierId,
            'verification_status' => self::VERIFICATION_VERIFIED,
            'verification_level' => $level,
            'badge_type' => $badgeType,
            'badge_expires_at' => now()->addYear(),
            'verification_notes' => $notes,
            'document_status' => self::DOCUMENT_APPROVED,
            'status' => self::STATUS_APPROVED,
        ]);
    }

    /**
     * Reject verification
     */
    public function rejectVerification($reason = null)
    {
        $this->update([
            'verification_status' => self::VERIFICATION_REJECTED,
            'document_status' => self::DOCUMENT_REJECTED,
            'document_rejection_reason' => $reason,
            'verification_notes' => $reason,
        ]);
    }

    /**
     * Scope for verified stores
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED)
            ->whereIn('verification_level', [self::LEVEL_VERIFIED, self::LEVEL_PREMIUM])
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for pending verification
     */
    public function scopePendingVerification($query)
    {
        return $query->where(function ($q) {
            // Sellers who have submitted documents but need verification
            $q->where('documents_submitted', true)
                ->whereIn('verification_status', [
                    self::VERIFICATION_PENDING,
                    self::VERIFICATION_UNDER_REVIEW,
                ])
                // Include sellers with uploaded documents regardless of status
                ->where(function ($subQ) {
                    $subQ->whereNotNull('identity_document_front')
                        ->orWhereNotNull('business_registration_document');
                });
        });
    }

    /**
     * Get all document fields
     */
    public function getDocumentFields()
    {
        $fields = [
            'business_registration_document' => [
                'label' => 'Business Registration Document',
                'required' => $this->business_type !== 'individual',
                'description' => 'Official business registration certificate',
            ],
            'tax_registration_document' => [
                'label' => 'Tax Registration Document',
                'required' => $this->business_type !== 'individual',
                'description' => 'Tax identification document',
            ],
            'identity_document_front' => [
                'label' => 'Identity Document (Front)',
                'required' => true,
                'description' => 'Front side of your ID (NRC, Passport, etc.)',
            ],
            'identity_document_back' => [
                'label' => 'Identity Document (Back)',
                'required' => false,
                'description' => 'Back side of your ID (if applicable)',
            ],
        ];

        return $fields;
    }

    /**
     * Get document URL
     */
    public function getDocumentUrl($field)
    {
        if (empty($this->$field)) {
            return null;
        }

        // Handle different URL formats
        if (str_starts_with($this->$field, 'http')) {
            return $this->$field;
        }

        if (str_starts_with($this->$field, 'storage/')) {
            return url($this->$field);
        }

        return url('storage/' . $this->$field);
    }

    /**
     * Get additional documents as array
     */
    public function getAdditionalDocuments()
    {
        $documents = $this->additional_documents ?? [];
        $result = [];

        foreach ($documents as $index => $document) {
            $result[] = [
                'id' => $index,
                'name' => $document['name'] ?? "Document " . ($index + 1),
                'url' => $this->getDocumentUrl($document['path'] ?? ''),
                'type' => $document['type'] ?? 'other',
                'uploaded_at' => $document['uploaded_at'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Relationship with verifier (admin)
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Relationship with business type
     */
    public function businessType()
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    /**
     * Get business type slug (convenience method)
     */
    public function getBusinessTypeSlugAttributes()
    {
        return $this->businessType ? $this->businessType->slug : $this->business_type;
    }

    /**
     * Get business type name (convenience method)
     */
    public function getBusinessTypeNameAttribute()
    {
        return $this->businessType ? $this->businessType->name : $this->business_type;
    }

    /**
     * Check if this is an individual business type
     */
    public function isIndividual()
    {
        if (!$this->businessType) {
            return $this->business_type->isIndividualType();
        }

        // Fallback to checking the string field
        return $this->business_type === 'individual';
    }

    /**
     * Relationship with user
     */
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
     * Check if onboarding is complete
     */
    public function isOnboardingComplete()
    {
        // All required fields must be filled with actual data (not empty strings)
        return !empty(trim($this->store_name)) &&
            (!empty($this->business_type_id) || !empty(trim($this->business_type))) &&
            !empty(trim($this->contact_email)) &&
            !empty(trim($this->contact_phone)) &&
            !empty(trim($this->address)) &&
            !empty(trim($this->city)) &&
            !empty(trim($this->state)) &&
            !empty(trim($this->country));
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

    public function getMissingFields()
    {
        $missing = [];

        if (empty($this->store_name)) {
            $missing[] = 'store_name';
        }

        if (empty($this->business_type_id)) {
            $missing[] = 'business_type';
        }

        if (empty($this->contact_email)) {
            $missing[] = 'contact_email';
        }

        if (empty($this->contact_phone)) {
            $missing[] = 'contact_phone';
        }

        if (empty($this->address)) {
            $missing[] = 'address';
        }

        if (empty($this->city)) {
            $missing[] = 'city';
        }

        if (empty($this->state)) {
            $missing[] = 'state';
        }

        if (empty($this->country)) {
            $missing[] = 'country';
        }

        return $missing;
    }

    public function deliveryAreas()
    {
        return $this->hasMany(DeliveryArea::class);
    }

    public function activeDeliveryAreas()
    {
        return $this->hasMany(DeliveryArea::class)->active()->deliverable()->orderBy('sort_order');
    }

    public function shippingSetting()
    {
        return $this->hasOne(ShippingSetting::class);
    }

}