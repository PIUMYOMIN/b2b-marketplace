<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    protected array $categoryMap = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Preload category map for quick lookups
        $this->buildCategoryMap();

        // Get all sellers (users with type 'seller')
        $sellers = User::where('type', 'seller')->get();
        if ($sellers->isEmpty()) {
            $this->command->error('No sellers found. Please run UserSeeder first.');
            return;
        }

        $products = [
            // ---------- Electronics (root id 1) ----------
            [
                'name_en' => 'Android Smartphone - 128GB',
                'name_mm' => 'Android စမတ်ဖုန်း - 128GB',
                'description_en' => 'Latest Android smartphone with 128GB storage, dual camera, and long battery life.',
                'description_mm' => '128GB သိုလှောင်မှု၊ ကင်မရာနှစ်လုံးနှင့် ဘက်ထရီသက်တမ်းရှည်သော နောက်ဆုံးထွက် Android စမတ်ဖုန်း။',
                'price' => 350000,
                'quantity' => 100,
                'category_slug' => 'smartphones',
                'brand' => 'TechPro',
                'model' => 'TP-X200',
                'color' => 'Midnight Black',
                'warranty' => '1 Year Manufacturer Warranty',
                'warranty_type' => 'manufacturer',
                'warranty_period' => '12 months',
                'min_order_unit' => 'piece',
                'moq' => 1,
                'shipping_cost' => 8000,
                'shipping_time' => '5-7 days',
                'is_featured' => true,
                'is_new' => true,
                'status' => 'approved',
            ],
            [
                'name_en' => 'Wireless Mouse - Special Offer',
                'name_mm' => 'ဝိုင်ယာလက်မဲ့ မောက်စ် - အထူးအဆိုပြုချက်',
                'description_en' => 'Wireless optical mouse with ergonomic design, 2.4GHz connectivity, and long battery life.',
                'description_mm' => 'အာဂိုနိုမစ်ဒီဇိုင်း၊ 2.4GHz ချိတ်ဆက်မှု၊ ဘက်ထရီသက်တမ်းရှည် ဝိုင်ယာလက်မဲ့မောက်စ်။',
                'price' => 15000,
                'discount_price' => 12000,
                'is_on_sale' => true,
                'sale_badge' => '20% OFF',
                'compare_at_price' => 15000,
                'quantity' => 75,
                'category_slug' => 'smartphones',   // subcategory under Electronics
                'brand' => 'TechPro',
                'color' => 'Black',
                'min_order_unit' => 'piece',
                'moq' => 1,
                'shipping_cost' => 1500,
                'shipping_time' => '2-3 days',
                'is_featured' => true,
                'status' => 'approved',
            ],
            [
                'name_en' => 'Portable Power Bank 20000mAh',
                'name_mm' => 'လက်ကိုင်ဘက်ထရီ ၂၀၀၀၀ mAh',
                'description_en' => 'High capacity power bank with fast charging, multiple ports, and LED display.',
                'description_mm' => 'စွမ်းရည်မြင့် လက်ကိုင်ဘက်ထရီ၊ အမြန်အားသွင်း၊ ပို့များစွာနှင့် LED မျက်နှာပြင်။',
                'price' => 35000,
                'quantity' => 120,
                'category_slug' => 'power-banks',   // under Mobile Phone Accessories (root 2)
                'brand' => 'PowerMax',
                'model' => 'PM-20000',
                'color' => 'Space Gray',
                'min_order_unit' => 'piece',
                'moq' => 3,
                'shipping_cost' => 3000,
                'shipping_time' => '3-5 days',
                'is_featured' => true,
                'status' => 'approved',
            ],
            [
                'name_en' => 'Wireless Bluetooth Earbuds',
                'name_mm' => 'ဝိုင်ယာလက်မဲ့ ဘလူးတုသ် နားကြပ်',
                'description_en' => 'True wireless earbuds with noise cancellation, 30 hours battery life, and waterproof design.',
                'description_mm' => 'ဆူညံသံဖျောက်၊ ဘက်ထရီသက်တမ်း ၃၀ နာရီနှင့် ရေခံဒီဇိုင်း။',
                'price' => 75000,
                'quantity' => 85,
                'category_slug' => 'audio-headphones', // under Electronics
                'brand' => 'SoundWave',
                'model' => 'SW-Pro',
                'color' => 'Black',
                'min_order_unit' => 'pair',
                'moq' => 2,
                'shipping_cost' => 2500,
                'shipping_time' => '4-6 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Portable Bluetooth Speaker',
                'name_mm' => 'လက်ကိုင် ဘလူးတုသ် စပီကာ',
                'description_en' => 'Waterproof portable speaker with 360° sound, 12 hours battery, and built-in microphone.',
                'description_mm' => 'ရေခံ၊ ၃၆၀° အသံ၊ ဘက်ထရီသက်တမ်း ၁၂ နာရီ၊ မိုက်ခ်ရိုဖုန်းပါ။',
                'price' => 28000,
                'quantity' => 90,
                'category_slug' => 'audio-headphones', // under Electronics
                'brand' => 'AudioFlow',
                'color' => 'Red',
                'min_order_unit' => 'piece',
                'moq' => 2,
                'shipping_cost' => 2500,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Home & Kitchen (root id 5) ----------
            [
                'name_en' => 'Electric Rice Cooker 10L',
                'name_mm' => 'လျှပ်စစ်ဆန်အိုး ၁၀လီတာ',
                'description_en' => 'Large capacity electric rice cooker with multiple cooking functions and keep-warm feature.',
                'description_mm' => 'အရွယ်အစားကြီး လျှပ်စစ်ဆန်အိုး၊ ချက်ပြုတ်လုပ်ဆောင်ချက်များစွာနှင့် အပူထိန်းစနစ်ပါဝင်။',
                'price' => 45000,
                'quantity' => 80,
                'category_slug' => 'kitchen-appliances', // subcategory under Home & Kitchen
                'brand' => 'QuickBoil',
                'material' => 'Stainless Steel',
                'color' => 'Silver',
                'min_order_unit' => 'piece',
                'moq' => 2,
                'shipping_cost' => 2000,
                'shipping_time' => '2-4 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Ceramic Dinner Set 16pcs',
                'name_mm' => 'စက္ကူပန်းကန် အစုံ ၁၆ပစ္စည်း',
                'description_en' => 'Complete ceramic dinner set for 4 persons, microwave and dishwasher safe.',
                'description_mm' => '၄ ဦးအတွက် စက္ကူပန်းကန်အစုံ၊ မိုက်ခရိုဝေ့နှင့် ပန်းကန်ဆေးစက်တွင် အသုံးပြုနိုင်။',
                'price' => 45000,
                'quantity' => 60,
                'category_slug' => 'kitchenware', // subcategory under Home & Kitchen
                'brand' => 'HomeStyle',
                'material' => 'Ceramic',
                'color' => 'White',
                'min_order_unit' => 'set',
                'moq' => 2,
                'shipping_cost' => 6000,
                'shipping_time' => '5-7 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Electric Kettle 1.7L',
                'name_mm' => 'လျှပ်စစ်ရေနွေးအိုး ၁.၇လီတာ',
                'description_en' => 'Stainless steel electric kettle with auto shut-off and boil-dry protection.',
                'description_mm' => 'သံမဏိလျှပ်စစ်ရေနွေးအိုး၊ အလိုအလျောက်ပိတ်ခြင်းနှင့် ရေခန်းခြောက်ကာကွယ်မှု။',
                'price' => 25000,
                'quantity' => 100,
                'category_slug' => 'kitchen-appliances', // under Home & Kitchen
                'brand' => 'QuickBoil',
                'material' => 'Stainless Steel',
                'color' => 'Silver',
                'min_order_unit' => 'piece',
                'moq' => 2,
                'shipping_cost' => 2000,
                'shipping_time' => '2-4 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Premium Coffee Maker - Limited Sale',
                'name_mm' => 'အထူးတန်း ကော်ဖီဖျော်စက် - အကန့်အသတ်ရောင်းချ',
                'description_en' => 'Automatic coffee maker with programmable settings, thermal carafe, and brew strength control.',
                'description_mm' => 'ပရိုဂရမ်လုပ်နိုင်၊ အပူထိန်းအိုး၊ ချက်ပြုတ်အားထိန်းချုပ်မှု ပါဝင်သော အလိုအလျောက်ကော်ဖီဖျော်စက်။',
                'price' => 65000,
                'discount_price' => 52000,
                'is_on_sale' => true,
                'sale_badge' => 'Limited Time',
                'compare_at_price' => 65000,
                'quantity' => 35,
                'category_slug' => 'kitchen-appliances',
                'brand' => 'BrewMaster',
                'material' => 'Stainless Steel',
                'color' => 'Silver',
                'min_order_unit' => 'piece',
                'moq' => 1,
                'shipping_cost' => 3000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Fashion & Clothing (root id 4) ----------
            [
                'name_en' => "Men's Casual Shirt",
                'name_mm' => 'ယောက်ျားဝတ် ပေါ့ပေါ့ပါးပါးရှပ်အင်္ကျီ',
                'description_en' => 'Comfortable cotton shirt for casual wear, available in multiple colors.',
                'description_mm' => 'ပေါ့ပေါ့ပါးပါးဝတ်ရန် သက်တောင့်သက်သာရှိသော ဝါဂွမ်းရှပ်အင်္ကျီ၊ အရောင်မျိုးစုံ။',
                'price' => 25000,
                'quantity' => 200,
                'category_slug' => 'mens-clothing',
                'brand' => 'Fashionista',
                'material' => 'Cotton',
                'color' => 'Blue',
                'min_order_unit' => 'piece',
                'moq' => 3,
                'shipping_cost' => 2000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],
            [
                'name_en' => "Women's Traditional Longyi",
                'name_mm' => 'မိန်းမဝတ် ထဘီ',
                'description_en' => 'Beautiful handwoven traditional longyi, perfect for formal occasions.',
                'description_mm' => 'လှပသော လက်ရက်ထဘီ၊ အခမ်းအနားများအတွက် သင့်တော်။',
                'price' => 35000,
                'quantity' => 150,
                'category_slug' => 'traditional-clothing',
                'brand' => 'Shan Traditions',
                'material' => 'Cotton',
                'color' => 'Multi',
                'min_order_unit' => 'piece',
                'moq' => 2,
                'shipping_cost' => 2500,
                'shipping_time' => '4-6 days',
                'status' => 'approved',
            ],

            // ---------- Food & Beverages (root id 6) ----------
            [
                'name_en' => 'Shwe Pa Yone Premium Rice 10kg',
                'name_mm' => 'ရွှေပါယုံ အထူးထွက်ဆန် ၁၀ကီလို',
                'description_en' => 'Premium quality Myanmar rice, perfect for daily consumption.',
                'description_mm' => 'အရည်အသွေးမြင့် မြန်မာဆန်၊ နေ့စဉ်စားသုံးရန် အကောင်းဆုံး။',
                'price' => 25000,
                'quantity' => 1000,
                'category_slug' => 'grains-rice',
                'brand' => 'Shwe Pa Yone',
                'origin' => 'Myanmar',
                'weight_kg' => 10,
                'min_order_unit' => 'bag',
                'moq' => 2,
                'shipping_cost' => 2000,
                'shipping_time' => '2-4 days',
                'status' => 'approved',
            ],

            // ---------- Beauty & Personal Care (root id 7) ----------
            [
                'name_en' => 'Thanakha Powder - Premium Grade',
                'name_mm' => 'သနပ်ခါးမှုန့် - အထူးတန်း',
                'description_en' => 'Traditional Myanmar thanakha powder, 100% natural from Thanakha tree bark.',
                'description_mm' => 'ရိုးရာမြန်မာသနပ်ခါးမှုန့်၊ သနပ်ခါးပင်အခေါက်မှ ၁၀၀% သဘာဝ။',
                'price' => 8000,
                'quantity' => 500,
                'category_slug' => 'skincare',
                'brand' => 'Myanmar Natural',
                'origin' => 'Central Myanmar',
                'weight_kg' => 0.5,
                'min_order_unit' => 'pack',
                'moq' => 20,
                'shipping_cost' => 2000,
                'shipping_time' => '2-4 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Coconut Hair Oil',
                'name_mm' => 'အုန်းဆီ',
                'description_en' => 'Pure coconut oil for hair care. Promotes hair growth and prevents hair fall.',
                'description_mm' => 'ဆံပင်ထိန်းသိမ်းရန်အတွက် စင်ကြယ်သောအုန်းဆီ။',
                'price' => 12000,
                'quantity' => 300,
                'category_slug' => 'haircare',
                'brand' => 'CocoPure',
                'origin' => 'Ayeyarwady Region',
                'weight_kg' => 1,
                'min_order_unit' => 'bottle',
                'moq' => 10,
                'shipping_cost' => 2500,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Sports & Fitness (root id 8) ----------
            [
                'name_en' => 'Fitness Dumbbell Set (20kg)',
                'name_mm' => 'ကျန်းမာရေး ဒမ့်ဘယ်လ်အစုံ (၂၀ကီလို)',
                'description_en' => 'Complete dumbbell set for home workouts. Includes adjustable weights and storage rack.',
                'description_mm' => 'အိမ်တွင်လေ့ကျင့်ခန်းလုပ်ရန် ပြည့်စုံသော ဒမ့်ဘယ်လ်အစုံ။',
                'price' => 85000,
                'quantity' => 50,
                'category_slug' => 'fitness-equipment',
                'brand' => 'FitStrong',
                'material' => 'Rubber Coated Steel',
                'weight_kg' => 20,
                'min_order_unit' => 'set',
                'moq' => 2,
                'shipping_cost' => 10000,
                'shipping_time' => '7-10 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Yoga Mat Premium',
                'name_mm' => 'ယောဂ ဖျား အထူးတန်း',
                'description_en' => 'Non-slip yoga mat with carrying strap, 10mm thickness for comfort.',
                'description_mm' => 'ချော်မလဲ၊ လွယ်အိတ်ပါ၊ ၁၀မီလီမီတာအထူ၊ သက်တောင့်သက်သာရှိ။',
                'price' => 18000,
                'quantity' => 200,
                'category_slug' => 'fitness-equipment',
                'brand' => 'FlexFit',
                'material' => 'PVC',
                'color' => 'Purple',
                'min_order_unit' => 'piece',
                'moq' => 5,
                'shipping_cost' => 2000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Office Supplies (root id 15) ----------
            [
                'name_en' => 'Office Desk Chair',
                'name_mm' => 'ရုံးခုံထိုင်ခုံ',
                'description_en' => 'Ergonomic office chair with lumbar support, adjustable height, and breathable mesh back.',
                'description_mm' => 'ခါးထောက်ပံ့ပိုး၊ အမြင့်ညှိနိုင်ပြီး လေဝင်လေထွက်ကောင်းသော ရုံးခုံထိုင်ခုံ။',
                'price' => 125000,
                'quantity' => 40,
                'category_slug' => 'office-furniture',
                'brand' => 'OfficeComfort',
                'material' => 'Mesh & Metal',
                'color' => 'Black',
                'dimensions' => ['height' => '120cm', 'width' => '60cm', 'depth' => '65cm'],
                'min_order_unit' => 'piece',
                'moq' => 1,
                'shipping_cost' => 15000,
                'shipping_time' => '7-10 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'Desk Organizer Set',
                'name_mm' => 'စားပွဲထိုင် စီစဉ်ရေးအစုံ',
                'description_en' => 'Wooden desk organizer with compartments for pens, papers, and stationery.',
                'description_mm' => 'သစ်သားစားပွဲထိုင်စီစဉ်ရေး၊ ဘောပင်များ၊ စက္ကူများနှင့် ရုံးသုံးပစ္စည်းများအတွက် ကန့်သတ်နေရာများ။',
                'price' => 22000,
                'quantity' => 120,
                'category_slug' => 'office-stationery',
                'brand' => 'OfficePlus',
                'material' => 'Wood',
                'min_order_unit' => 'set',
                'moq' => 3,
                'shipping_cost' => 2500,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],
            [
                'name_en' => 'LED Desk Lamp',
                'name_mm' => 'LED စားပွဲထိုင် မီးလုံး',
                'description_en' => 'Adjustable LED desk lamp with 3 color temperatures and touch controls.',
                'description_mm' => 'ချိန်ညှိနိုင်သော LED စားပွဲထိုင်မီးလုံး၊ အရောင် ၃ မျိုးနှင့် ထိတွေ့ထိန်းချုပ်မှု။',
                'price' => 18000,
                'quantity' => 80,
                'category_slug' => 'office-electronics',
                'brand' => 'LightPro',
                'color' => 'White',
                'min_order_unit' => 'piece',
                'moq' => 2,
                'shipping_cost' => 2000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Toys & Games (root id 16) ----------
            [
                'name_en' => 'Educational Toy Set',
                'name_mm' => 'ပညာရေးဆိုင်ရာ ကလေးကစားစရာ အစုံ',
                'description_en' => 'Montessori-inspired educational toys for children ages 3-6, promotes cognitive development.',
                'description_mm' => '၃-၆ နှစ်ကလေးများအတွက် Montessori နည်းလမ်းမျိုးစုံ ပညာရေးကစားစရာ။',
                'price' => 35000,
                'quantity' => 60,
                'category_slug' => 'educational-toys',
                'brand' => 'EduPlay',
                'material' => 'Wood',
                'min_order_unit' => 'set',
                'moq' => 2,
                'shipping_cost' => 4000,
                'shipping_time' => '4-6 days',
                'status' => 'approved',
            ],

            // ---------- Travel & Luggage (root id 21) ----------
            [
                'name_en' => 'Backpack with USB Port',
                'name_mm' => 'USB ပို့ပါ ကျောပိုးအိတ်',
                'description_en' => 'Water-resistant backpack with built-in USB charging port and multiple compartments.',
                'description_mm' => 'ရေခံ၊ တပ်ဆင်ထားသော USB အားသွင်းပို့နှင့် ကန့်သတ်နေရာများစွာ။',
                'price' => 28000,
                'quantity' => 150,
                'category_slug' => 'travel-bags',
                'brand' => 'TravelPro',
                'material' => 'Polyester',
                'color' => 'Black',
                'min_order_unit' => 'piece',
                'moq' => 3,
                'shipping_cost' => 3000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Industrial & Construction (root id 9) ----------
            [
                'name_en' => 'Construction Safety Helmet',
                'name_mm' => 'ဆောက်လုပ်ရေး လုံခြုံရေး ဦးထုပ်',
                'description_en' => 'Industrial safety helmet with adjustable headband, meets safety standards for construction sites.',
                'description_mm' => 'ချိန်ညှိနိုင်သော ခေါင်းပတ်ပါ၊ ဆောက်လုပ်ရေးလုပ်ငန်းခွင်အတွက် လုံခြုံရေးစံချိန်စံညွှန်းများနှင့် ကိုက်ညီ။',
                'price' => 8000,
                'quantity' => 300,
                'category_slug' => 'safety-equipment',
                'brand' => 'SafeBuild',
                'material' => 'ABS Plastic',
                'color' => 'Yellow',
                'min_order_unit' => 'piece',
                'moq' => 10,
                'shipping_cost' => 1500,
                'shipping_time' => '2-4 days',
                'status' => 'approved',
            ],

            // ---------- Automotive & Vehicles (root id 11) ----------
            [
                'name_en' => 'Car Engine Oil 5W-30',
                'name_mm' => 'ကားအင်ဂျင်ဆီ 5W-30',
                'description_en' => 'Full synthetic engine oil, improves fuel efficiency and engine performance.',
                'description_mm' => 'အပြည့်အစုံ အင်ဂျင်ဆီ၊ လောင်စာစွမ်းဆောင်ရည်နှင့် အင်ဂျင်စွမ်းဆောင်ရည်ကို မြှင့်တင်ပေး။',
                'price' => 35000,
                'quantity' => 200,
                'category_slug' => 'lubricants-oils',
                'brand' => 'AutoPro',
                'weight_kg' => 4,
                'min_order_unit' => 'bottle',
                'moq' => 5,
                'shipping_cost' => 3000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Health & Medical (root id 13) ----------
            [
                'name_en' => 'Medical Face Mask 50pcs',
                'name_mm' => 'ဆေးဘက်ဆိုင်ရာ မျက်နှာဖုံး ၅၀ခု',
                'description_en' => '3-ply surgical face masks, bacterial filtration efficiency >95%, comfortable ear loops.',
                'description_mm' => 'အလွှာ ၃ ထပ်ပါ ခွဲစိတ်ခန်းသုံး မျက်နှာဖုံး၊ ဘက်တီးရီးယားစစ်ထုတ်မှု ၉၅% ထက်ပိုမို။',
                'price' => 15000,
                'quantity' => 1000,
                'category_slug' => 'medical-supplies',
                'brand' => 'MediSafe',
                'min_order_unit' => 'pack',
                'moq' => 20,
                'shipping_cost' => 2000,
                'shipping_time' => '2-3 days',
                'status' => 'approved',
            ],

            // ---------- Books & Stationery (root id 12) ----------
            [
                'name_en' => 'English-Myanmar Dictionary',
                'name_mm' => 'အင်္ဂလိပ်-မြန်မာ အဘိဓာန်',
                'description_en' => 'Comprehensive English-Myanmar dictionary with over 50,000 entries.',
                'description_mm' => 'အင်္ဂလိပ်-မြန်မာ အဘိဓာန်၊ ဝေါဟာရပေါင်း ၅၀,၀၀၀ ကျော် ပါဝင်။',
                'price' => 25000,
                'quantity' => 200,
                'category_slug' => 'books',
                'brand' => 'Knowledge House',
                'min_order_unit' => 'piece',
                'moq' => 2,
                'shipping_cost' => 2000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Art & Craft (root id 18) ----------
            [
                'name_en' => 'Acrylic Paint Set 12 Colors',
                'name_mm' => 'အကရီလစ်ဆေး ၁၂ရောင် အစုံ',
                'description_en' => 'High-quality acrylic paint set for artists and hobbyists.',
                'description_mm' => 'အနုပညာရှင်များနှင့် ဝါသနာရှင်များအတွက် အရည်အသွေးမြင့် အကရီလစ်ဆေးအစုံ။',
                'price' => 18000,
                'quantity' => 150,
                'category_slug' => 'art-supplies',
                'brand' => 'ArtMaster',
                'min_order_unit' => 'set',
                'moq' => 3,
                'shipping_cost' => 2000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Garden & Outdoor (root id 19) ----------
            [
                'name_en' => 'Gardening Tool Set 5pcs',
                'name_mm' => 'ဥယျာဉ်ခြံကိရိယာအစုံ ၅ခု',
                'description_en' => 'Essential gardening tools including trowel, pruner, and gloves.',
                'description_mm' => 'မရှိမဖြစ် ဥယျာဉ်ခြံကိရိယာများ၊ တူးစူး၊ ညှပ်စက်၊ လက်အိတ်တို့ ပါဝင်။',
                'price' => 22000,
                'quantity' => 100,
                'category_slug' => 'gardening-tools',
                'brand' => 'GreenThumb',
                'material' => 'Steel/Plastic',
                'min_order_unit' => 'set',
                'moq' => 2,
                'shipping_cost' => 2500,
                'shipping_time' => '4-6 days',
                'status' => 'approved',
            ],

            // ---------- Baby & Kids (root id 20) ----------
            [
                'name_en' => 'Baby Soft Toys Set',
                'name_mm' => 'ကလေးအတွက် ပျော့ကစားစရာအစုံ',
                'description_en' => 'Soft and safe plush toys for infants, machine washable.',
                'description_mm' => 'နူးညံ့ပြီး လုံခြုံသော အရုပ်များ၊ စက်ဖြင့်လျှော်ဖွပ်နိုင်။',
                'price' => 18000,
                'quantity' => 120,
                'category_slug' => 'baby-toys',
                'brand' => 'BabyLove',
                'material' => 'Plush',
                'min_order_unit' => 'set',
                'moq' => 2,
                'shipping_cost' => 2000,
                'shipping_time' => '3-5 days',
                'status' => 'approved',
            ],

            // ---------- Digital Products (root id 24) ----------
            [
                'name_en' => 'Business Logo Design Service',
                'name_mm' => 'လုပ်ငန်းတံဆိပ် ဒီဇိုင်းဝန်ဆောင်မှု',
                'description_en' => 'Professional custom logo design for your business, unlimited revisions.',
                'description_mm' => 'သင့်လုပ်ငန်းအတွက် ပရော်ဖက်ရှင်နယ် လိုဂိုဒီဇိုင်း၊ အကန့်အသတ်မဲ့ ပြင်ဆင်နိုင်။',
                'price' => 50000,
                'quantity' => 999, // digital product, unlimited stock
                'category_slug' => 'digital-art',
                'brand' => 'DesignPro',
                'min_order_unit' => 'service',
                'moq' => 1,
                'shipping_cost' => 0,
                'shipping_time' => 'digital delivery',
                'status' => 'approved',
            ],
        ];

        // Insert each product
        foreach ($products as $productData) {
            // Get category_id from slug
            $categorySlug = $productData['category_slug'];
            if (!isset($this->categoryMap[$categorySlug])) {
                $this->command->warn("Category slug '{$categorySlug}' not found, skipping product.");
                continue;
            }
            $productData['category_id'] = $this->categoryMap[$categorySlug];
            unset($productData['category_slug']);

            // Generate slug_en from name_en
            $productData['slug_en'] = Str::slug($productData['name_en']);

            // Ensure unique slug
            $count = Product::where('slug_en', 'LIKE', $productData['slug_en'] . '%')->count();
            if ($count > 0) {
                $productData['slug_en'] = $productData['slug_en'] . '-' . ($count + 1);
            }

            // Assign a random seller
            $productData['seller_id'] = $sellers->random()->id;

            // Set default values if not provided
            $productData['is_active'] = $productData['is_active'] ?? true;
            $productData['status'] = $productData['status'] ?? 'approved';
            $productData['min_order_unit'] = $productData['min_order_unit'] ?? 'piece';
            $productData['moq'] = $productData['moq'] ?? 1;

            // Create product
            Product::create($productData);
        }

        $this->command->info('Products seeded successfully!');
    }

    /**
     * Build a map of category slug => id for quick lookup.
     */
    private function buildCategoryMap(): void
    {
        $categories = Category::all(['id', 'slug_en']);
        foreach ($categories as $cat) {
            $this->categoryMap[$cat->slug_en] = $cat->id;
        }
    }
}