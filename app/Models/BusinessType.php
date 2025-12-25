<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_registration',
        'requires_tax_document',
        'requires_identity_document',
        'requires_business_certificate',
        'additional_requirements',
        'is_active',
        'sort_order',
        'icon',
        'color'
    ];

    protected $casts = [
        'requires_registration' => 'boolean',
        'requires_tax_document' => 'boolean',
        'requires_identity_document' => 'boolean',
        'requires_business_certificate' => 'boolean',
        'is_active' => 'boolean',
        'additional_requirements' => 'array'
    ];

    /**
     * Scope active business types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if this is an individual business type
     */
    public function isIndividualType()
    {
        return !$this->requires_registration &&
            !$this->requires_tax_document &&
            !$this->requires_business_certificate;
    }

    /**
     * Get document requirements for this business type
     */
    public function getDocumentRequirements()
    {
        $requirements = [];

        // Identity documents are always required
        $requirements[] = [
            'type' => 'identity_document_front',
            'label' => 'Front of Identity Document',
            'description' => 'Clear photo of the front side of ID card/passport',
            'required' => true
        ];

        $requirements[] = [
            'type' => 'identity_document_back',
            'label' => 'Back of Identity Document',
            'description' => 'Clear photo of the back side of ID card/passport',
            'required' => true
        ];

        // Business registration
        if ($this->requires_registration) {
            $requirements[] = [
                'type' => 'business_registration_document',
                'label' => 'Business Registration Certificate',
                'description' => 'Official business registration document',
                'required' => true
            ];
        }

        // Tax document
        if ($this->requires_tax_document) {
            $requirements[] = [
                'type' => 'tax_registration_document',
                'label' => 'Tax Registration Certificate',
                'description' => 'Tax identification registration document',
                'required' => true
            ];
        }

        // Business certificate
        if ($this->requires_business_certificate) {
            $requirements[] = [
                'type' => 'business_certificate',
                'label' => 'Business License/Certificate',
                'description' => 'Business operating license or certificate',
                'required' => true
            ];
        }

        // Additional documents (always optional)
        $requirements[] = [
            'type' => 'additional_documents',
            'label' => 'Additional Supporting Documents',
            'description' => 'Any other supporting documents',
            'required' => false
        ];

        return $requirements;
    }

    /**
     * Get human-readable requirements summary
     */
    public function getRequirementsSummary()
    {
        $summary = 'Required: Identity Documents (Front & Back)';

        if ($this->requires_registration) {
            $summary .= ', Business Registration Certificate';
        }

        if ($this->requires_tax_document) {
            $summary .= ', Tax Registration Document';
        }

        if ($this->requires_business_certificate) {
            $summary .= ', Business License/Certificate';
        }

        $summary .= '. Optional: Additional Supporting Documents';

        return $summary;
    }

    /**
     * Relationship with seller profiles
     */
    public function sellerProfiles()
    {
        return $this->hasMany(SellerProfile::class);
    }
}
