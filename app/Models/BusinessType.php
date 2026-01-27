<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_mm',
        'description_en',
        'description_mm',
        'slug_en',
        'slug_mm',
        'requires_registration',
        'requires_tax_document',
        'requires_identity_document',
        'requires_business_certificate',
        'is_active',
        'sort_order',
        'icon',
        'color',
        'commission_rate',
        'monthly_fee',
        'transaction_fee',
        'minimum_sale_amount',
        'verification_level',
    ];

    protected $casts = [
        'requires_registration' => 'boolean',
        'requires_tax_document' => 'boolean',
        'requires_identity_document' => 'boolean',
        'requires_business_certificate' => 'boolean',
        'commission_rate' => 'decimal:2',
        'monthly_fee' => 'decimal:2',
        'transaction_fee' => 'decimal:2',
        'minimum_sale_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'additional_requirements' => 'array',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
        return $query->orderBy('sort_order')
            ->orderBy('name_en');
    }

    /**
     * Check if this is an individual business type
     */
    public function isIndividualType()
    {
        return in_array($this->slug_en, ['individual', 'sole_proprietor']);
    }

    /**
     * Get name based on language
     */
    public function getNameAttribute()
    {
        $lang = request()->get('lang', 'en');
        return $lang === 'mm' ? $this->name_mm : $this->name_en;
    }

    /**
     * Get description based on language
     */
    public function getDescriptionAttribute()
    {
        $lang = request()->get('lang', 'en');
        return $lang === 'mm' ? $this->description_mm : $this->description_en;
    }


    /**
     * Get slug based on language
     */
    public function getSlugAttribute()
    {
        $lang = request()->get('lang', 'en');
        return $lang === 'mm' ? $this->slug_mm : $this->slug_en;
    }

    /**
     * Get document requirements for this business type
     */
    public function getDocumentRequirements(): array
    {
        $lang = request()->get('lang', 'en'); // current language
        $requirements = [];

        // Identity documents (always required)
        $requirements[] = [
            'type' => 'identity_document_front',
            'label' => $lang === 'mm' ? 'ကိုယ်ပိုင် မှတ်ပုံတင် အရင်ဘက်' : 'Front of Identity Document',
            'description' => $lang === 'mm' ? 'ID ကတ်/Passport ရဲ့ရှေ့ဘက် ပုံရှင်းလင်းစွာ' : 'Clear photo of the front side of ID card/passport',
            'required' => true
        ];

        $requirements[] = [
            'type' => 'identity_document_back',
            'label' => $lang === 'mm' ? 'ကိုယ်ပိုင် မှတ်ပုံတင် နောက်ဘက်' : 'Back of Identity Document',
            'description' => $lang === 'mm' ? 'ID ကတ်/Passport ရဲ့နောက်ဘက် ပုံရှင်းလင်းစွာ' : 'Clear photo of the back side of ID card/passport',
            'required' => true
        ];

        // Business registration
        if ($this->requires_registration) {
            $requirements[] = [
                'type' => 'business_registration_document',
                'label' => $lang === 'mm' ? 'လုပ်ငန်း မှတ်ပုံတင်လက်မှတ်' : 'Business Registration Certificate',
                'description' => $lang === 'mm' ? 'မှတ်ပုံတင်ထားသည့် လုပ်ငန်း စာရွက်စာတမ်း' : 'Official business registration document',
                'required' => true
            ];
        }

        // Tax document
        if ($this->requires_tax_document) {
            $requirements[] = [
                'type' => 'tax_registration_document',
                'label' => $lang === 'mm' ? 'အခွန်မှတ်ပုံတင်လက်မှတ်' : 'Tax Registration Certificate',
                'description' => $lang === 'mm' ? 'အခွန်မှတ်ပုံတင် စာရွက်စာတမ်း' : 'Tax identification registration document',
                'required' => true
            ];
        }

        // Business certificate
        if ($this->requires_business_certificate) {
            $requirements[] = [
                'type' => 'business_certificate',
                'label' => $lang === 'mm' ? 'လုပ်ငန်း လိုင်စင်/လက်မှတ်' : 'Business License/Certificate',
                'description' => $lang === 'mm' ? 'လုပ်ငန်း လုပ်ကိုင်ခွင့် လိုင်စင်/လက်မှတ်' : 'Business operating license or certificate',
                'required' => true
            ];
        }

        // Always include a default optional document
        $requirements[] = [
            'type' => 'additional_documents',
            'label' => $lang === 'mm' ? 'အပိုထောက်ပံ့စာရွက်စာတမ်းများ' : 'Additional Supporting Documents',
            'description' => $lang === 'mm' ? 'အခြားလိုအပ်သည့်စာရွက်စာတမ်းများ' : 'Any other supporting documents',
            'required' => false
        ];

        // Merge any custom additional requirements from the database
        if (!empty($this->additional_requirements) && is_array($this->additional_requirements)) {
            foreach ($this->additional_requirements as $req) {
                $requirements[] = [
                    'type' => $req['type'] ?? 'additional_documents',
                    'label' => $lang === 'mm' ? ($req['label_mm'] ?? $req['label_en'] ?? 'အပိုထောက်ပံ့စာရွက်စာတမ်းများ')
                        : ($req['label_en'] ?? 'Additional Supporting Documents'),
                    'description' => $lang === 'mm' ? ($req['description_mm'] ?? $req['description_en'] ?? 'အခြားလိုအပ်သည့်စာရွက်စာတမ်းများ')
                        : ($req['description_en'] ?? 'Any other supporting documents'),
                    'required' => $req['required'] ?? false,
                ];
            }
        }

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
