<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BusinessType;
use Illuminate\Support\Str;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = [
            [
                'name_en' => 'Individual / Sole Proprietor',
                'name_mm' => 'တစ်ဦးတည်းလုပ်ငန်း',
                'description_en' => 'A business owned and operated by one person',
                'description_mm' => 'လူတစ်ဦးတည်းပိုင်ဆိုင်ပြီး လည်ပတ်သော လုပ်ငန်း',
                'slug_en' => 'individual',
                'slug_mm' => 'ta-oo-tae-lote-ngan',
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
                'name_en' => 'Private Limited Company',
                'name_mm' => 'ပုဂ္ဂလိက ကုမ္ပဏီ',
                'description_en' => 'A registered company with limited liability',
                'description_mm' => 'အကန့်အသတ်ရှိသော တာဝန်ခံမှုဖြင့် မှတ်ပုံတင်ထားသော ကုမ္ပဏီ',
                'slug_en' => 'company',
                'slug_mm' => 'private-company',
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
                'name_en' => 'Retail Business',
                'name_mm' => 'လက်လီရောင်းချလုပ်ငန်း',
                'description_en' => 'Business that sells directly to consumers',
                'description_mm' => 'သုံးစွဲသူများထံ တိုက်ရိုက်ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'retail',
                'slug_mm' => 'retail-mm',
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
                'name_en' => 'Wholesale Business',
                'name_mm' => 'လက်ကားရောင်းချလုပ်ငန်း',
                'description_en' => 'Business that sells in bulk to retailers',
                'description_mm' => 'လက်လီဆိုင်များသို့ အစုလိုက်ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'wholesale',
                'slug_mm' => 'wholesale-mm',
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
                'name_en' => 'Partnership',
                'name_mm' => 'ပူးပေါင်းလုပ်ငန်း',
                'description_en' => 'Business owned by two or more individuals',
                'description_mm' => 'လူနှစ်ဦး သို့မဟုတ် အများပိုင် လုပ်ငန်း',
                'slug_en' => 'partnership',
                'slug_mm' => 'partnership-mm',
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
            BusinessType::updateOrCreate(
                ['slug_en' => $type['slug_en']],
                $type
            );
        }
    }
}