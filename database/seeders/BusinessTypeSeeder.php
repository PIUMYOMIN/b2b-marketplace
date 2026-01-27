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
                'commission_rate' => 5.00, // 5% commission for individual sellers
                'monthly_fee' => 0.00, // No monthly fee
                'transaction_fee' => 1.50, // 1.5% transaction fee
                'minimum_sale_amount' => 0.00,
                'verification_level' => 'basic',
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
                'commission_rate' => 3.50, // 3.5% commission for companies
                'monthly_fee' => 5000.00, // 5,000 MMK monthly fee
                'transaction_fee' => 1.00, // 1% transaction fee
                'minimum_sale_amount' => 100000.00, // 100,000 MMK minimum
                'verification_level' => 'advanced',
            ],
            [
                'name_en' => 'Retail Business',
                'name_mm' => 'လက်လီရောင်းချလုပ်ငန်း',
                'description_en' => 'Business that sells directly to consumers',
                'description_mm' => 'သုံးစွဲသူများထံ တိုက်ရိုက်ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'retail',
                'slug_mm' => 'let-li-yaung-gyauk-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 3,
                'icon' => 'store',
                'color' => 'purple',
                'commission_rate' => 4.00, // 4% commission
                'monthly_fee' => 3000.00, // 3,000 MMK monthly fee
                'transaction_fee' => 1.25, // 1.25% transaction fee
                'minimum_sale_amount' => 50000.00, // 50,000 MMK minimum
                'verification_level' => 'standard',
            ],
            [
                'name_en' => 'Wholesale Business',
                'name_mm' => 'လက်ကားရောင်းချလုပ်ငန်း',
                'description_en' => 'Business that sells in bulk to retailers',
                'description_mm' => 'လက်လီဆိုင်များသို့ အစုလိုက်ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'wholesale',
                'slug_mm' => 'let-ka-yaung-gyauk-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 4,
                'icon' => 'truck',
                'color' => 'orange',
                'commission_rate' => 2.50, // 2.5% commission for wholesale
                'monthly_fee' => 10000.00, // 10,000 MMK monthly fee
                'transaction_fee' => 0.75, // 0.75% transaction fee
                'minimum_sale_amount' => 500000.00, // 500,000 MMK minimum
                'verification_level' => 'advanced',
            ],
            [
                'name_en' => 'Partnership',
                'name_mm' => 'ပူးပေါင်းလုပ်ငန်း',
                'description_en' => 'Business owned by two or more individuals',
                'description_mm' => 'လူနှစ်ဦး သို့မဟုတ် အများပိုင် လုပ်ငန်း',
                'slug_en' => 'partnership',
                'slug_mm' => 'pu-baung-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 5,
                'icon' => 'users',
                'color' => 'yellow',
                'commission_rate' => 4.50, // 4.5% commission
                'monthly_fee' => 4000.00, // 4,000 MMK monthly fee
                'transaction_fee' => 1.00, // 1% transaction fee
                'minimum_sale_amount' => 100000.00, // 100,000 MMK minimum
                'verification_level' => 'standard',
            ],
            [
                'name_en' => 'Home Based Business',
                'name_mm' => 'အိမ်တွင်း လုပ်ငန်း',
                'description_en' => 'Small business operated from home',
                'description_mm' => 'အိမ်မှ လည်ပတ်သော သေးငယ်သည့် လုပ်ငန်း',
                'slug_en' => 'home-based',
                'slug_mm' => 'ain-twain-lote-ngan',
                'requires_registration' => false,
                'requires_tax_document' => false,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 6,
                'icon' => 'home',
                'color' => 'pink',
                'commission_rate' => 6.00, // 6% commission
                'monthly_fee' => 0.00, // No monthly fee
                'transaction_fee' => 2.00, // 2% transaction fee
                'minimum_sale_amount' => 0.00,
                'verification_level' => 'basic',
            ],
            [
                'name_en' => 'Manufacturer',
                'name_mm' => 'ထုတ်လုပ်သူ',
                'description_en' => 'Business that produces goods',
                'description_mm' => 'ကုန်ပစ္စည်းများ ထုတ်လုပ်သော လုပ်ငန်း',
                'slug_en' => 'manufacturer',
                'slug_mm' => 'htote-lote-thu',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 7,
                'icon' => 'cog',
                'color' => 'indigo',
                'commission_rate' => 2.00, // 2% commission for manufacturers
                'monthly_fee' => 15000.00, // 15,000 MMK monthly fee
                'transaction_fee' => 0.50, // 0.5% transaction fee
                'minimum_sale_amount' => 1000000.00, // 1,000,000 MMK minimum
                'verification_level' => 'premium',
            ],
            [
                'name_en' => 'Importer',
                'name_mm' => 'သွင်းကုန် လုပ်ငန်း',
                'description_en' => 'Business that imports goods from other countries',
                'description_mm' => 'နိုင်ငံခြားမှ ကုန်ပစ္စည်းများ တင်သွင်းသော လုပ်ငန်း',
                'slug_en' => 'importer',
                'slug_mm' => 'swin-kone-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 8,
                'icon' => 'globe',
                'color' => 'teal',
                'commission_rate' => 2.50, // 2.5% commission
                'monthly_fee' => 20000.00, // 20,000 MMK monthly fee
                'transaction_fee' => 0.75, // 0.75% transaction fee
                'minimum_sale_amount' => 2000000.00, // 2,000,000 MMK minimum
                'verification_level' => 'premium',
            ],
            [
                'name_en' => 'Exporter',
                'name_mm' => 'ထုတ်ကုန် လုပ်ငန်း',
                'description_en' => 'Business that exports goods to other countries',
                'description_mm' => 'နိုင်ငံခြားသို့ ကုန်ပစ္စည်းများ တင်ပို့သော လုပ်ငန်း',
                'slug_en' => 'exporter',
                'slug_mm' => 'htote-kone-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 9,
                'icon' => 'plane',
                'color' => 'cyan',
                'commission_rate' => 2.00, // 2% commission
                'monthly_fee' => 25000.00, // 25,000 MMK monthly fee
                'transaction_fee' => 0.50, // 0.5% transaction fee
                'minimum_sale_amount' => 5000000.00, // 5,000,000 MMK minimum
                'verification_level' => 'premium',
            ],
            [
                'name_en' => 'Agriculture Business',
                'name_mm' => 'စိုက်ပျိုးရေး လုပ်ငန်း',
                'description_en' => 'Business involved in farming and agriculture',
                'description_mm' => 'စိုက်ပျိုးရေးနှင့် ဆက်စပ်သော လုပ်ငန်း',
                'slug_en' => 'agriculture',
                'slug_mm' => 'saite-pyoe-yae-lote-ngan',
                'requires_registration' => false,
                'requires_tax_document' => false,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 10,
                'icon' => 'leaf',
                'color' => 'emerald',
                'commission_rate' => 3.00, // 3% commission
                'monthly_fee' => 2000.00, // 2,000 MMK monthly fee
                'transaction_fee' => 1.00, // 1% transaction fee
                'minimum_sale_amount' => 50000.00, // 50,000 MMK minimum
                'verification_level' => 'standard',
            ],
            [
                'name_en' => 'Food & Beverage',
                'name_mm' => 'အစားအသောက် လုပ်ငန်း',
                'description_en' => 'Business selling food and drinks',
                'description_mm' => 'အစားအသောက်များ ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'food-beverage',
                'slug_mm' => 'a-sar-a-thout-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 11,
                'icon' => 'utensils',
                'color' => 'red',
                'commission_rate' => 5.00, // 5% commission
                'monthly_fee' => 5000.00, // 5,000 MMK monthly fee
                'transaction_fee' => 1.50, // 1.5% transaction fee
                'minimum_sale_amount' => 100000.00, // 100,000 MMK minimum
                'verification_level' => 'standard',
            ],
            [
                'name_en' => 'Service Provider',
                'name_mm' => 'ဝန်ဆောင်မှု လုပ်ငန်း',
                'description_en' => 'Business providing services instead of products',
                'description_mm' => 'ကုန်ပစ္စည်းအစား ဝန်ဆောင်မှုပေးသော လုပ်ငန်း',
                'slug_en' => 'service-provider',
                'slug_mm' => 'wun-saung-mhu-lote-ngan',
                'requires_registration' => false,
                'requires_tax_document' => false,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 12,
                'icon' => 'wrench',
                'color' => 'gray',
                'commission_rate' => 8.00, // 8% commission for services
                'monthly_fee' => 3000.00, // 3,000 MMK monthly fee
                'transaction_fee' => 2.50, // 2.5% transaction fee
                'minimum_sale_amount' => 0.00,
                'verification_level' => 'basic',
            ],
            [
                'name_en' => 'Handicraft Business',
                'name_mm' => 'လက်မှုပညာ လုပ်ငန်း',
                'description_en' => 'Business selling handmade crafts and products',
                'description_mm' => 'လက်ဖြင့်ပြုလုပ်သော လက်မှုပစ္စည်းများ ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'handicraft',
                'slug_mm' => 'let-hmu-pyi-nya-lote-ngan',
                'requires_registration' => false,
                'requires_tax_document' => false,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 13,
                'icon' => 'palette',
                'color' => 'amber',
                'commission_rate' => 4.00, // 4% commission
                'monthly_fee' => 1000.00, // 1,000 MMK monthly fee
                'transaction_fee' => 1.00, // 1% transaction fee
                'minimum_sale_amount' => 0.00,
                'verification_level' => 'basic',
            ],
            [
                'name_en' => 'Fashion & Clothing',
                'name_mm' => 'ဖက်ရှင် နှင့် အဝတ်အထည် လုပ်ငန်း',
                'description_en' => 'Business selling clothing and fashion items',
                'description_mm' => 'အဝတ်အထည်နှင့် ဖက်ရှင်ပစ္စည်းများ ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'fashion',
                'slug_mm' => 'fashion-a-wut-a-hte-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => false,
                'is_active' => true,
                'sort_order' => 14,
                'icon' => 'tshirt',
                'color' => 'rose',
                'commission_rate' => 5.50, // 5.5% commission
                'monthly_fee' => 4000.00, // 4,000 MMK monthly fee
                'transaction_fee' => 1.75, // 1.75% transaction fee
                'minimum_sale_amount' => 50000.00, // 50,000 MMK minimum
                'verification_level' => 'standard',
            ],
            [
                'name_en' => 'Electronics Business',
                'name_mm' => 'လျှပ်စစ် ပစ္စည်း လုပ်ငန်း',
                'description_en' => 'Business selling electronic devices and appliances',
                'description_mm' => 'လျှပ်စစ်ပစ္စည်းများနှင့် ကိရိယာများ ရောင်းချသော လုပ်ငန်း',
                'slug_en' => 'electronics',
                'slug_mm' => 'hlyut-sit-pyi-si-lote-ngan',
                'requires_registration' => true,
                'requires_tax_document' => true,
                'requires_identity_document' => true,
                'requires_business_certificate' => true,
                'is_active' => true,
                'sort_order' => 15,
                'icon' => 'tv',
                'color' => 'violet',
                'commission_rate' => 3.50, // 3.5% commission
                'monthly_fee' => 8000.00, // 8,000 MMK monthly fee
                'transaction_fee' => 1.00, // 1% transaction fee
                'minimum_sale_amount' => 200000.00, // 200,000 MMK minimum
                'verification_level' => 'standard',
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
