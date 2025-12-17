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

    // Relationships
    public function sellerProfiles()
    {
        return $this->hasMany(SellerProfile::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function getDocumentRequirements()
    {
        $requirements = [];

        // Always require identity document
        if ($this->requires_identity_document) {
            $requirements[] = [
                'type' => 'identity_document_front',
                'label' => 'Identity Document (Front)',
                'required' => true,
                'description' => 'Front side of your ID document (NRC, Passport, etc.)',
                'accepted_formats' => 'jpg,jpeg,png',
                'max_size' => '2MB'
            ];

            $requirements[] = [
                'type' => 'identity_document_back',
                'label' => 'Identity Document (Back)',
                'required' => true,
                'description' => 'Back side of your ID document',
                'accepted_formats' => 'jpg,jpeg,png',
                'max_size' => '2MB'
            ];
        }

        if ($this->requires_registration) {
            $requirements[] = [
                'type' => 'business_registration_document',
                'label' => 'Business Registration Document',
                'required' => true,
                'description' => 'Official business registration certificate',
                'accepted_formats' => 'pdf,jpg,jpeg,png',
                'max_size' => '5MB'
            ];
        }

        if ($this->requires_tax_document) {
            $requirements[] = [
                'type' => 'tax_registration_document',
                'label' => 'Tax Registration Document',
                'required' => true,
                'description' => 'Tax identification document',
                'accepted_formats' => 'pdf,jpg,jpeg,png',
                'max_size' => '5MB'
            ];
        }

        if ($this->requires_business_certificate) {
            $requirements[] = [
                'type' => 'certificate',
                'label' => 'Business Certificate',
                'required' => true,
                'description' => 'Business operation certificate',
                'accepted_formats' => 'pdf,jpg,jpeg,png',
                'max_size' => '5MB'
            ];
        }

        return $requirements;
    }

    public function isIndividualType()
    {
        return !$this->requires_registration &&
               !$this->requires_tax_document &&
               !$this->requires_business_certificate;
    }
}
