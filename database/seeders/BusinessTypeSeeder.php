<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BusinessType;
use Illuminate\Support\Str;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businessTypes = [
            [
                'name' => 'Individual/Sole Proprietor',
                'slug' => 'individual',
                'description' => 'A business owned and operated by one person',
                'requires_registration' => false,
                'requires_tax_document' => false,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 1,
                'icon' => 'user',
                'color' => 'green',
            ],
            [
                'name' => 'Private Limited Company',
                'slug' => 'company',
                'description' => 'A registered company with limited liability',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 2,
                'icon' => 'building',
                'color' => 'blue',
            ],
            [
                'name' => 'Retail Business',
                'slug' => 'retail',
                'description' => 'Business that sells directly to consumers',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 3,
                'icon' => 'store',
                'color' => 'purple',
            ],
            [
                'name' => 'Wholesale Business',
                'slug' => 'wholesale',
                'description' => 'Business that sells in bulk to retailers',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 4,
                'icon' => 'truck',
                'color' => 'orange',
            ],
            [
                'name' => 'Partnership',
                'slug' => 'partnership',
                'description' => 'Business owned by two or more individuals',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 5,
                'icon' => 'users',
                'color' => 'yellow',
            ],
        ];

        foreach ($businessTypes as $type) {
            BusinessType::create($type);
        }
    }
}