<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Clear existing categories
        Category::query()->delete();

        // First, create all main categories (roots)
        $electronics = Category::create([
            'name_en' => 'Electronics',
            'name_mm' => 'အီလက်ထရောနစ်',
            'description_en' => 'Electronic devices and accessories',
            'description_mm' => 'အီလက်ထရောနစ်ပစ္စည်းများနှင့် အရန်ပစ္စည်းများ',
            'slug_en' => 'electronics',
            'slug_mm' => 'အီလက်ထရောနစ်',
            'commission_rate' => 0.10,
            'is_active' => true,
        ]);
        $electronics->makeRoot()->save();

        $mobilephoneaccessories = Category::create([
            'name_en' => 'Mobile Phone Accessories',
            'name_mm' => 'မိုဘိုင်းဖုန်းအရန်ပစ္စည်းများ',
            'description_en' => 'Mobile phone accessories',
            'description_mm' => 'မိုဘိုင်းဖုန်းအရန်ပစ္စည်းများ',
            'slug_en' => 'mobile-phone-accessories',
            'slug_mm' => 'မိုဘိုင်းဖုန်းအရန်ပစ္စည်းများ',
            'commission_rate' => 0.10,
            'is_active' => true,
        ]);
        $mobilephoneaccessories->makeRoot()->save();

        $computeraccessories = Category::create([
            'name_en' => 'Computer Accessories',
            'name_mm' => 'ကွန်ပျူတာအရန်ပစ္စည်းများ',
            'description_en' => 'Computer accessories',
            'description_mm' => 'ကွန်ပျူတာအရန်ပစ္စည်းများ',
            'slug_en' => 'computer-accessories',
            'slug_mm' => 'ကွန်ပျူတာအရန်ပစ္စည်းများ',
            'commission_rate' => 0.10,
            'is_active' => true,
        ]);
        $computeraccessories->makeRoot()->save();

        $fashion = Category::create([
            'name_en' => 'Fashion & Clothing',
            'name_mm' => 'ဖက်ရှင်နှင့် အဝတ်အစား',
            'description_en' => 'Clothing, shoes, and accessories',
            'description_mm' => 'အဝတ်အစား၊ ဖိနပ်နှင့် အရန်ပစ္စည်းများ',
            'slug_en' => 'fashion-clothing',
            'slug_mm' => 'ဖက်ရှင်-အဝတ်အစား',
            'commission_rate' => 0.12,
            'is_active' => true,
        ]);
        $fashion->makeRoot()->save();

        $homeKitchen = Category::create([
            'name_en' => 'Home & Kitchen',
            'name_mm' => 'အိမ်သုံးနှင့် မီးဖိုချောင်',
            'description_en' => 'Home appliances and kitchenware',
            'description_mm' => 'အိမ်သုံးပစ္စည်းများနှင့် မီးဖိုချောင်သုံးပစ္စည်းများ',
            'slug_en' => 'home-kitchen',
            'slug_mm' => 'အိမ်သုံး-မီးဖိုချောင်',
            'commission_rate' => 0.09,
            'is_active' => true,
        ]);
        $homeKitchen->makeRoot()->save();

        $food = Category::create([
            'name_en' => 'Food & Beverages',
            'name_mm' => 'အစားအသောက်နှင့် အဖျော်ယမကာ',
            'description_en' => 'Food items and beverages',
            'description_mm' => 'အစားအစာများနှင့် အဖျော်ယမကာများ',
            'slug_en' => 'food-beverages',
            'slug_mm' => 'အစားအသောက်-အဖျော်ယမကာ',
            'commission_rate' => 0.08,
            'is_active' => true,
        ]);
        $food->makeRoot()->save();

        $beauty = Category::create([
            'name_en' => 'Beauty & Personal Care',
            'name_mm' => 'အလှအပနှင့် ကိုယ်ရေးကိုယ်တာစောင့်ရှောက်မှု',
            'description_en' => 'Beauty products and personal care items',
            'description_mm' => 'အလှအပပစ္စည်းများနှင့် ကိုယ်ရေးကိုယ်တာစောင့်ရှောက်မှုပစ္စည်းများ',
            'slug_en' => 'beauty-personal-care',
            'slug_mm' => 'အလှအပ-ကိုယ်ရေးကိုယ်တာ',
            'commission_rate' => 0.11,
            'is_active' => true,
        ]);
        $beauty->makeRoot()->save();

        $sports = Category::create([
            'name_en' => 'Sports & Fitness',
            'name_mm' => 'အားကစားနှင့် ကျန်းမာရေး',
            'description_en' => 'Sports equipment and fitness gear',
            'description_mm' => 'အားကစားပစ္စည်းများနှင့် ကျန်းမာရေးကိရိယာများ',
            'slug_en' => 'sports-fitness',
            'slug_mm' => 'အားကစား-ကျန်းမာရေး',
            'commission_rate' => 0.10,
            'is_active' => true,
        ]);
        $sports->makeRoot()->save();

        $industrial = Category::create([
            'name_en' => 'Industrial & Construction',
            'name_mm' => 'စက်မှုနှင့် ဆောက်လုပ်ရေး',
            'description_en' => 'Industrial equipment and construction materials',
            'description_mm' => 'စက်မှုကိရိယာများနှင့် ဆောက်လုပ်ရေးပစ္စည်းများ',
            'slug_en' => 'industrial-construction',
            'slug_mm' => 'စက်မှု-ဆောက်လုပ်ရေး',
            'commission_rate' => 0.07,
            'is_active' => true,
        ]);
        $industrial->makeRoot()->save();

        $agriculture = Category::create([
            'name_en' => 'Agriculture',
            'name_mm' => 'စိုက်ပျိုးရေး',
            'description_en' => 'Agricultural equipment and supplies',
            'description_mm' => 'စိုက်ပျိုးရေးကိရိယာများနှင့် ပစ္စည်းများ',
            'slug_en' => 'agriculture',
            'slug_mm' => 'စိုက်ပျိုးရေး',
            'commission_rate' => 0.06,
            'is_active' => true,
        ]);
        $agriculture->makeRoot()->save();

        $automotive = Category::create([
            'name_en' => 'Automotive & Vehicles',
            'name_mm' => 'ယာဉ်နှင့် ယာဉ်ကြီးများ',
            'description_en' => 'Vehicles, parts, and automotive supplies',
            'description_mm' => 'ယာဉ်များ၊ အစိတ်အပိုင်းများနှင့် ယာဉ်သုံးပစ္စည်းများ',
            'slug_en' => 'automotive-vehicles',
            'slug_mm' => 'ယာဉ်-ယာဉ်ကြီး',
            'commission_rate' => 0.08,
            'is_active' => true,
        ]);
        $automotive->makeRoot()->save();

        $books = Category::create([
            'name_en' => 'Books & Stationery',
            'name_mm' => 'စာအုပ်နှင့် စာရေးကိရိယာများ',
            'description_en' => 'Books, magazines, and stationery items',
            'description_mm' => 'စာအုပ်များ၊ မဂ္ဂဇင်းများနှင့် စာရေးကိရိယာပစ္စည်းများ',
            'slug_en' => 'books-stationery',
            'slug_mm' => 'စာအုပ်-စာရေးကိရိယာ',
            'commission_rate' => 0.05,
            'is_active' => true,
        ]);
        $books->makeRoot()->save();

        $health = Category::create([
            'name_en' => 'Health & Medical',
            'name_mm' => 'ကျန်းမာရေးနှင့် ဆေးဘက်ဆိုင်ရာ',
            'description_en' => 'Medical equipment, pharmaceuticals, and health supplies',
            'description_mm' => 'ဆေးဘက်ဆိုင်ရာကိရိယာများ၊ ဆေးဝါးများနှင့် ကျန်းမာရေးပစ္စည်းများ',
            'slug_en' => 'health-medical',
            'slug_mm' => 'ကျန်းမာရေး-ဆေးဘက်ဆိုင်ရာ',
            'commission_rate' => 0.08,
            'is_active' => true,
        ]);
        $health->makeRoot()->save();

        // NEW CATEGORIES
        $pets = Category::create([
            'name_en' => 'Pets & Animals',
            'name_mm' => 'အိမ်မွေးတိရစ္ဆာန်များနှင့် တိရစ္ဆာန်များ',
            'description_en' => 'Pet supplies, food, and accessories',
            'description_mm' => 'အိမ်မွေးတိရစ္ဆာန်ပစ္စည်းများ၊ အစာနှင့် အရန်ပစ္စည်းများ',
            'slug_en' => 'pets-animals',
            'slug_mm' => 'အိမ်မွေးတိရစ္ဆာန်-တိရစ္ဆာန်',
            'commission_rate' => 0.09,
            'is_active' => true,
        ]);
        $pets->makeRoot()->save();

        $office = Category::create([
            'name_en' => 'Office Supplies',
            'name_mm' => 'ရုံးသုံးပစ္စည်းများ',
            'description_en' => 'Office furniture, equipment, and supplies',
            'description_mm' => 'ရုံးသုံးပရိဘောဂ၊ ကိရိယာများနှင့် ပစ္စည်းများ',
            'slug_en' => 'office-supplies',
            'slug_mm' => 'ရုံးသုံးပစ္စည်း',
            'commission_rate' => 0.07,
            'is_active' => true,
        ]);
        $office->makeRoot()->save();

        $toys = Category::create([
            'name_en' => 'Toys & Games',
            'name_mm' => 'ကစားစရာများနှင့် ဂိမ်းများ',
            'description_en' => 'Toys, games, and entertainment items',
            'description_mm' => 'ကစားစရာများ၊ ဂိမ်းများနှင့် ဖျော်ဖြေရေးပစ္စည်းများ',
            'slug_en' => 'toys-games',
            'slug_mm' => 'ကစားစရာ-ဂိမ်း',
            'commission_rate' => 0.10,
            'is_active' => true,
        ]);
        $toys->makeRoot()->save();

        $musical = Category::create([
            'name_en' => 'Musical Instruments',
            'name_mm' => 'တူရိယာများ',
            'description_en' => 'Musical instruments and equipment',
            'description_mm' => 'တူရိယာများနှင့် ကိရိယာများ',
            'slug_en' => 'musical-instruments',
            'slug_mm' => 'တူရိယာ',
            'commission_rate' => 0.12,
            'is_active' => true,
        ]);
        $musical->makeRoot()->save();

        $art = Category::create([
            'name_en' => 'Art & Craft',
            'name_mm' => 'အနုပညာနှင့် လက်မှုပစ္စည်းများ',
            'description_en' => 'Art supplies, craft materials, and handmade items',
            'description_mm' => 'အနုပညာပစ္စည်းများ၊ လက်မှုပစ္စည်းများနှင့် လက်လုပ်ပစ္စည်းများ',
            'slug_en' => 'art-craft',
            'slug_mm' => 'အနုပညာ-လက်မှု',
            'commission_rate' => 0.09,
            'is_active' => true,
        ]);
        $art->makeRoot()->save();

        $garden = Category::create([
            'name_en' => 'Garden & Outdoor',
            'name_mm' => 'ဥယျာဉ်ခြံနှင့် အပြင်ပန်း',
            'description_en' => 'Gardening tools, outdoor furniture, and supplies',
            'description_mm' => 'ဥယျာဉ်ခြံကိရိယာများ၊ အပြင်ပန်းပရိဘောဂနှင့် ပစ္စည်းများ',
            'slug_en' => 'garden-outdoor',
            'slug_mm' => 'ဥယျာဉ်ခြံ-အပြင်ပန်း',
            'commission_rate' => 0.08,
            'is_active' => true,
        ]);
        $garden->makeRoot()->save();

        $baby = Category::create([
            'name_en' => 'Baby & Kids',
            'name_mm' => 'ကလေးများနှင့် ကလေးငယ်များ',
            'description_en' => 'Baby products, kids clothing, and toys',
            'description_mm' => 'ကလေးပစ္စည်းများ၊ ကလေးအဝတ်အစားများနှင့် ကစားစရာများ',
            'slug_en' => 'baby-kids',
            'slug_mm' => 'ကလေး-ကလေးငယ်',
            'commission_rate' => 0.10,
            'is_active' => true,
        ]);
        $baby->makeRoot()->save();

        $travel = Category::create([
            'name_en' => 'Travel & Luggage',
            'name_mm' => 'ခရီးသွားလာရေးနှင့် ခရီးဆောင်အိတ်များ',
            'description_en' => 'Luggage, travel accessories, and bags',
            'description_mm' => 'ခရီးဆောင်အိတ်များ၊ ခရီးသွားအရန်ပစ္စည်းများနှင့် အိတ်များ',
            'slug_en' => 'travel-luggage',
            'slug_mm' => 'ခရီးသွား-ခရီးဆောင်အိတ်',
            'commission_rate' => 0.11,
            'is_active' => true,
        ]);
        $travel->makeRoot()->save();

        $party = Category::create([
            'name_en' => 'Party & Events',
            'name_mm' => 'ပါတီနှင့် အခမ်းအနားများ',
            'description_en' => 'Party supplies, decorations, and event materials',
            'description_mm' => 'ပါတီပစ္စည်းများ၊ အလှဆင်ပစ္စည်းများနှင့် အခမ်းအနားပစ္စည်းများ',
            'slug_en' => 'party-events',
            'slug_mm' => 'ပါတီ-အခမ်းအနား',
            'commission_rate' => 0.12,
            'is_active' => true,
        ]);
        $party->makeRoot()->save();

        $religious = Category::create([
            'name_en' => 'Religious & Spiritual',
            'name_mm' => 'ဘာသာရေးနှင့် စိတ်ဝိညာဉ်ရေးရာ',
            'description_en' => 'Religious items, spiritual products, and offerings',
            'description_mm' => 'ဘာသာရေးပစ္စည်းများ၊ စိတ်ဝိညာဉ်ရေးရာပစ္စည်းများနှင့် လှူဒါန်းပစ္စည်းများ',
            'slug_en' => 'religious-spiritual',
            'slug_mm' => 'ဘာသာရေး-စိတ်ဝိညာဉ်ရေး',
            'commission_rate' => 0.07,
            'is_active' => true,
        ]);
        $religious->makeRoot()->save();

        $digital = Category::create([
            'name_en' => 'Digital Products',
            'name_mm' => 'ဒစ်ဂျစ်တယ်ထုတ်ကုန်များ',
            'description_en' => 'Software, e-books, digital services',
            'description_mm' => 'ဆော့ဖ်ဝဲ၊ အီးစာအုပ်များ၊ ဒစ်ဂျစ်တယ်ဝန်ဆောင်မှုများ',
            'slug_en' => 'digital-products',
            'slug_mm' => 'ဒစ်ဂျစ်တယ်ထုတ်ကုန်',
            'commission_rate' => 0.20,
            'is_active' => true,
        ]);
        $digital->makeRoot()->save();

        // Now create sub-categories
        $this->createElectronicsSubCategories($electronics);
        $this->createMobilePhoneAccessoriesSubCategories($mobilephoneaccessories);
        $this->createComputerAccessoriesSubCategories($computeraccessories);
        $this->createFashionSubCategories($fashion);
        $this->createHomeKitchenSubCategories($homeKitchen);
        $this->createFoodSubCategories($food);
        $this->createBeautySubCategories($beauty);
        $this->createSportsSubCategories($sports);
        $this->createIndustrialSubCategories($industrial);
        $this->createAgricultureSubCategories($agriculture);
        $this->createAutomotiveSubCategories($automotive);
        $this->createBooksSubCategories($books);
        $this->createHealthSubCategories($health);
        $this->createPetsSubCategories($pets);
        $this->createOfficeSubCategories($office);
        $this->createToysSubCategories($toys);
        $this->createMusicalSubCategories($musical);
        $this->createArtSubCategories($art);
        $this->createGardenSubCategories($garden);
        $this->createBabySubCategories($baby);
        $this->createTravelSubCategories($travel);
        $this->createPartySubCategories($party);
        $this->createReligiousSubCategories($religious);
        $this->createDigitalSubCategories($digital);

        $this->command->info('Categories seeded successfully with parent-child relationships!');
    }

    private function createElectronicsSubCategories(Category $parent)
    {
        $smartphones = Category::create([
            'name_en' => 'Smartphones',
            'name_mm' => 'စမတ်ဖုန်းများ',
            'slug_en' => 'smartphones',
            'slug_mm' => 'စမတ်ဖုန်းများ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $smartphones->appendToNode($parent)->save();

        $laptops = Category::create([
            'name_en' => 'Laptops & Computers',
            'name_mm' => 'လက်တော့ပ်နှင့် ကွန်ပျူတာများ',
            'slug_en' => 'laptops-computers',
            'slug_mm' => 'လက်တော့ပ်-ကွန်ပျူတာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $laptops->appendToNode($parent)->save();

        $tvs = Category::create([
            'name_en' => 'TVs & Monitors',
            'name_mm' => 'တီဗွီနှင့် မော်နီတာများ',
            'slug_en' => 'tvs-monitors',
            'slug_mm' => 'တီဗွီ-မော်နီတာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $tvs->appendToNode($parent)->save();

        $audio = Category::create([
            'name_en' => 'Audio & Headphones',
            'name_mm' => 'အသံနှင့် နားကြပ်များ',
            'slug_en' => 'audio-headphones',
            'slug_mm' => 'အသံ-နားကြပ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $audio->appendToNode($parent)->save();

        $cameras = Category::create([
            'name_en' => 'Cameras',
            'name_mm' => 'ကင်မရာများ',
            'slug_en' => 'cameras',
            'slug_mm' => 'ကင်မရာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $cameras->appendToNode($parent)->save();

        $homeAppliances = Category::create([
            'name_en' => 'Home Appliances',
            'name_mm' => 'အိမ်သုံးပစ္စည်းများ',
            'slug_en' => 'home-appliances',
            'slug_mm' => 'အိမ်သုံးပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $homeAppliances->appendToNode($parent)->save();

        $kitchenAppliances = Category::create([
            'name_en' => 'Kitchen Appliances',
            'name_mm' => 'မီးဖိုချောင်သုံးပစ္စည်းများ',
            'slug_en' => 'kitchen-appliances',
            'slug_mm' => 'မီးဖိုချောင်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $kitchenAppliances->appendToNode($parent)->save();

        $gaming = Category::create([
            'name_en' => 'Gaming Consoles',
            'name_mm' => 'ဂိမ်းကြိုးဝိုင်းများ',
            'slug_en' => 'gaming-consoles',
            'slug_mm' => 'ဂိမ်းကြိုးဝိုင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $gaming->appendToNode($parent)->save();

        $drones = Category::create([
            'name_en' => 'Drones',
            'name_mm' => 'ဒရုန်းများ',
            'slug_en' => 'drones',
            'slug_mm' => 'ဒရုန်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $drones->appendToNode($parent)->save();
    }

    private function createMobilePhoneAccessoriesSubCategories(Category $parent)
    {
        $cases = Category::create([
            'name_en' => 'Phone Cases',
            'name_mm' => 'ဖုန်းအခွံများ',
            'slug_en' => 'phone-cases',
            'slug_mm' => 'ဖုန်းအခွံ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $cases->appendToNode($parent)->save();

        $chargers = Category::create([
            'name_en' => 'Chargers & Cables',
            'name_mm' => 'အားသွင်းကိရိယာနှင့် ကြိုးများ',
            'slug_en' => 'chargers-cables',
            'slug_mm' => 'အားသွင်းကိရိယာ-ကြိုး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $chargers->appendToNode($parent)->save();

        $screenProtectors = Category::create([
            'name_en' => 'Screen Protectors',
            'name_mm' => 'မျက်နှာပြင်ကာကွယ်ရေးများ',
            'slug_en' => 'screen-protectors',
            'slug_mm' => 'မျက်နှာပြင်ကာကွယ်ရေး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $screenProtectors->appendToNode($parent)->save();

        $earphones = Category::create([
            'name_en' => 'Earphones & Headsets',
            'name_mm' => 'နားကြပ်နှင့် ခေါင်းဆောင်းများ',
            'slug_en' => 'earphones-headsets',
            'slug_mm' => 'နားကြပ်-ခေါင်းဆောင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $earphones->appendToNode($parent)->save();

        $powerBanks = Category::create([
            'name_en' => 'Power Banks',
            'name_mm' => 'ပါဝါဘဏ်များ',
            'slug_en' => 'power-banks',
            'slug_mm' => 'ပါဝါဘဏ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $powerBanks->appendToNode($parent)->save();
    }

    private function createComputerAccessoriesSubCategories(Category $parent)
    {
        $keyboards = Category::create([
            'name_en' => 'Keyboards & Mice',
            'name_mm' => 'ကွန်ပျူတာကီးဘုတ်နှင့် မောင်းများ',
            'slug_en' => 'keyboards-mice',
            'slug_mm' => 'ကီးဘုတ်-မောက်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $keyboards->appendToNode($parent)->save();

        $monitors = Category::create([
            'name_en' => 'Monitors',
            'name_mm' => 'မော်နီတာများ',
            'slug_en' => 'monitors',
            'slug_mm' => 'မော်နီတာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $monitors->appendToNode($parent)->save();

        $storage = Category::create([
            'name_en' => 'Storage Devices',
            'name_mm' => 'သိုလှောင်ကိရိယာများ',
            'slug_en' => 'storage-devices',
            'slug_mm' => 'သိုလှောင်ကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $storage->appendToNode($parent)->save();

        $printers = Category::create([
            'name_en' => 'Printers & Scanners',
            'name_mm' => 'ပရင့်တာနှင့် စကင်နာများ',
            'slug_en' => 'printers-scanners',
            'slug_mm' => 'ပရင့်တာ-စကင်နာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $printers->appendToNode($parent)->save();

        $networking = Category::create([
            'name_en' => 'Networking',
            'name_mm' => 'ကွန်ယက်ချိတ်ဆက်ရေး',
            'slug_en' => 'networking',
            'slug_mm' => 'ကွန်ယက်ချိတ်ဆက်ရေး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $networking->appendToNode($parent)->save();
    }

    private function createFashionSubCategories(Category $parent)
    {
        $mensClothing = Category::create([
            'name_en' => "Men's Clothing",
            'name_mm' => 'ယောက်ျားဝတ်အဝတ်အစား',
            'slug_en' => 'mens-clothing',
            'slug_mm' => 'ယောက်ျားဝတ်အဝတ်အစား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $mensClothing->appendToNode($parent)->save();

        $womensClothing = Category::create([
            'name_en' => "Women's Clothing",
            'name_mm' => 'မိန်းမဝတ်အဝတ်အစား',
            'slug_en' => 'womens-clothing',
            'slug_mm' => 'မိန်းမဝတ်အဝတ်အစား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $womensClothing->appendToNode($parent)->save();

        $traditional = Category::create([
            'name_en' => 'Traditional Clothing',
            'name_mm' => 'ရိုးရာအဝတ်အစား',
            'slug_en' => 'traditional-clothing',
            'slug_mm' => 'ရိုးရာအဝတ်အစား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $traditional->appendToNode($parent)->save();

        $shoes = Category::create([
            'name_en' => 'Shoes & Footwear',
            'name_mm' => 'ဖိနပ်နှင့် ခြေနင်းများ',
            'slug_en' => 'shoes-footwear',
            'slug_mm' => 'ဖိနပ်-ခြေနင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $shoes->appendToNode($parent)->save();

        $bags = Category::create([
            'name_en' => 'Bags & Accessories',
            'name_mm' => 'အိတ်နှင့် အရန်ပစ္စည်းများ',
            'slug_en' => 'bags-accessories',
            'slug_mm' => 'အိတ်-အရန်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $bags->appendToNode($parent)->save();
    }

    private function createHomeKitchenSubCategories(Category $parent)
    {
        $furniture = Category::create([
            'name_en' => 'Furniture',
            'name_mm' => 'ပရိဘောဂ',
            'slug_en' => 'furniture',
            'slug_mm' => 'ပရိဘောဂ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $furniture->appendToNode($parent)->save();

        $homeDecor = Category::create([
            'name_en' => 'Home Decor',
            'name_mm' => 'အိမ်အလှဆင်',
            'slug_en' => 'home-decor',
            'slug_mm' => 'အိမ်အလှဆင်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $homeDecor->appendToNode($parent)->save();

        $kitchenware = Category::create([
            'name_en' => 'Kitchenware',
            'name_mm' => 'မီးဖိုချောင်သုံးပစ္စည်း',
            'slug_en' => 'kitchenware',
            'slug_mm' => 'မီးဖိုချောင်သုံးပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $kitchenware->appendToNode($parent)->save();

        $bedding = Category::create([
            'name_en' => 'Bedding & Bath',
            'name_mm' => 'အိပ်ရာခင်းနှင့် ရေချိုးခန်း',
            'slug_en' => 'bedding-bath',
            'slug_mm' => 'အိပ်ရာခင်း-ရေချိုးခန်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $bedding->appendToNode($parent)->save();

        $lighting = Category::create([
            'name_en' => 'Lighting',
            'name_mm' => 'မီးအလင်းရောင်',
            'slug_en' => 'lighting',
            'slug_mm' => 'မီးအလင်းရောင်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $lighting->appendToNode($parent)->save();

        $storage = Category::create([
            'name_en' => 'Storage & Organization',
            'name_mm' => 'သိုလှောင်ရေးနှင့် စနစ်တကျထားရှိမှု',
            'slug_en' => 'storage-organization',
            'slug_mm' => 'သိုလှောင်ရေး-စနစ်တကျထားရှိမှု',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $storage->appendToNode($parent)->save();
    }

    private function createFoodSubCategories(Category $parent)
    {
        $grains = Category::create([
            'name_en' => 'Grains & Rice',
            'name_mm' => 'စပါးနှင့် ဆန်',
            'slug_en' => 'grains-rice',
            'slug_mm' => 'စပါး-ဆန်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $grains->appendToNode($parent)->save();

        $snacks = Category::create([
            'name_en' => 'Snacks',
            'name_mm' => 'သွားရည်စာများ',
            'slug_en' => 'snacks',
            'slug_mm' => 'သွားရည်စာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $snacks->appendToNode($parent)->save();

        $beverages = Category::create([
            'name_en' => 'Beverages',
            'name_mm' => 'အဖျော်ယမကာများ',
            'slug_en' => 'beverages',
            'slug_mm' => 'အဖျော်ယမကာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $beverages->appendToNode($parent)->save();

        $spices = Category::create([
            'name_en' => 'Spices & Condiments',
            'name_mm' => 'ဟင်းခတ်အမွှေးအကြိုင်နှင့် အချိုပွဲများ',
            'slug_en' => 'spices-condiments',
            'slug_mm' => 'ဟင်းခတ်အမွှေးအကြိုင်-အချိုပွဲ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $spices->appendToNode($parent)->save();

        $canned = Category::create([
            'name_en' => 'Canned Food',
            'name_mm' => 'ဘူးသွပ်အစားအစာများ',
            'slug_en' => 'canned-food',
            'slug_mm' => 'ဘူးသွပ်အစားအစာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $canned->appendToNode($parent)->save();

        $dairy = Category::create([
            'name_en' => 'Dairy Products',
            'name_mm' => 'နို့ထွက်ပစ္စည်းများ',
            'slug_en' => 'dairy-products',
            'slug_mm' => 'နို့ထွက်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $dairy->appendToNode($parent)->save();

        $fresh = Category::create([
            'name_en' => 'Fresh Produce',
            'name_mm' => 'လတ်ဆတ်သောထွက်ကုန်များ',
            'slug_en' => 'fresh-produce',
            'slug_mm' => 'လတ်ဆတ်ထွက်ကုန်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $fresh->appendToNode($parent)->save();
    }

    private function createBeautySubCategories(Category $parent)
    {
        $skincare = Category::create([
            'name_en' => 'Skincare',
            'name_mm' => 'အသားအရေထိန်းသိမ်းခြင်း',
            'slug_en' => 'skincare',
            'slug_mm' => 'အသားအရေထိန်းသိမ်းခြင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $skincare->appendToNode($parent)->save();

        $haircare = Category::create([
            'name_en' => 'Haircare',
            'name_mm' => 'ဆံပင်ထိန်းသိမ်းခြင်း',
            'slug_en' => 'haircare',
            'slug_mm' => 'ဆံပင်ထိန်းသိမ်းခြင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $haircare->appendToNode($parent)->save();

        $makeup = Category::create([
            'name_en' => 'Makeup',
            'name_mm' => 'မိတ်ကပ်',
            'slug_en' => 'makeup',
            'slug_mm' => 'မိတ်ကပ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $makeup->appendToNode($parent)->save();

        $fragrances = Category::create([
            'name_en' => 'Fragrances',
            'name_mm' => 'ရနံ့သင်းပစ္စည်းများ',
            'slug_en' => 'fragrances',
            'slug_mm' => 'ရနံ့သင်းပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $fragrances->appendToNode($parent)->save();

        $hygiene = Category::create([
            'name_en' => 'Personal Hygiene',
            'name_mm' => 'ကိုယ်ရေးကိုယ်တာသန့်ရှင်းရေး',
            'slug_en' => 'personal-hygiene',
            'slug_mm' => 'ကိုယ်ရေးကိုယ်တာသန့်ရှင်းရေး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $hygiene->appendToNode($parent)->save();
    }

    private function createSportsSubCategories(Category $parent)
    {
        $sportsClothing = Category::create([
            'name_en' => 'Sports Clothing',
            'name_mm' => 'အားကစားဝတ်စုံများ',
            'slug_en' => 'sports-clothing',
            'slug_mm' => 'အားကစားဝတ်စုံ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $sportsClothing->appendToNode($parent)->save();

        $outdoor = Category::create([
            'name_en' => 'Outdoor Sports',
            'name_mm' => 'အပြင်ပန်းအားကစား',
            'slug_en' => 'outdoor-sports',
            'slug_mm' => 'အပြင်ပန်းအားကစား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $outdoor->appendToNode($parent)->save();

        $indoor = Category::create([
            'name_en' => 'Indoor Games',
            'name_mm' => 'အိမ်တွင်းဂိမ်းများ',
            'slug_en' => 'indoor-games',
            'slug_mm' => 'အိမ်တွင်းဂိမ်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $indoor->appendToNode($parent)->save();

        $cycling = Category::create([
            'name_en' => 'Cycling',
            'name_mm' => 'စက်ဘီးစီးခြင်း',
            'slug_en' => 'cycling',
            'slug_mm' => 'စက်ဘီးစီးခြင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $cycling->appendToNode($parent)->save();

        $water = Category::create([
            'name_en' => 'Water Sports',
            'name_mm' => 'ရေကစား',
            'slug_en' => 'water-sports',
            'slug_mm' => 'ရေကစား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $water->appendToNode($parent)->save();
    }

    private function createIndustrialSubCategories(Category $parent)
    {
        $construction = Category::create([
            'name_en' => 'Construction Materials',
            'name_mm' => 'ဆောက်လုပ်ရေးပစ္စည်းများ',
            'slug_en' => 'construction-materials',
            'slug_mm' => 'ဆောက်လုပ်ရေးပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $construction->appendToNode($parent)->save();

        $tools = Category::create([
            'name_en' => 'Tools & Machinery',
            'name_mm' => 'ကိရိယာများနှင့် စက်ယန္တရားများ',
            'slug_en' => 'tools-machinery',
            'slug_mm' => 'ကိရိယာ-စက်ယန္တရား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $tools->appendToNode($parent)->save();

        $safety = Category::create([
            'name_en' => 'Safety Equipment',
            'name_mm' => 'လုံခြုံရေးကိရိယာများ',
            'slug_en' => 'safety-equipment',
            'slug_mm' => 'လုံခြုံရေးကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $safety->appendToNode($parent)->save();

        $electrical = Category::create([
            'name_en' => 'Electrical Supplies',
            'name_mm' => 'လျှပ်စစ်ပစ္စည်းများ',
            'slug_en' => 'electrical-supplies',
            'slug_mm' => 'လျှပ်စစ်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $electrical->appendToNode($parent)->save();

        $plumbing = Category::create([
            'name_en' => 'Plumbing',
            'name_mm' => 'ပိုက်လုပ်ငန်း',
            'slug_en' => 'plumbing',
            'slug_mm' => 'ပိုက်လုပ်ငန်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $plumbing->appendToNode($parent)->save();

        $hardware = Category::create([
            'name_en' => 'Hardware',
            'name_mm' => 'ဟာ့ဒ်ဝဲ',
            'slug_en' => 'hardware',
            'slug_mm' => 'ဟာ့ဒ်ဝဲ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $hardware->appendToNode($parent)->save();
    }

    private function createAgricultureSubCategories(Category $parent)
    {
        $seeds = Category::create([
            'name_en' => 'Seeds & Plants',
            'name_mm' => 'မျိုးစေ့နှင့် အပင်များ',
            'slug_en' => 'seeds-plants',
            'slug_mm' => 'မျိုးစေ့-အပင်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $seeds->appendToNode($parent)->save();

        $fertilizers = Category::create([
            'name_en' => 'Fertilizers',
            'name_mm' => 'မြေဩဇာများ',
            'slug_en' => 'fertilizers',
            'slug_mm' => 'မြေဩဇာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $fertilizers->appendToNode($parent)->save();

        $pesticides = Category::create([
            'name_en' => 'Pesticides',
            'name_mm' => 'ပိုးသတ်ဆေးများ',
            'slug_en' => 'pesticides',
            'slug_mm' => 'ပိုးသတ်ဆေး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $pesticides->appendToNode($parent)->save();

        $farmingTools = Category::create([
            'name_en' => 'Farming Tools',
            'name_mm' => 'လယ်ယာကိရိယာများ',
            'slug_en' => 'farming-tools',
            'slug_mm' => 'လယ်ယာကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $farmingTools->appendToNode($parent)->save();

        $irrigation = Category::create([
            'name_en' => 'Irrigation',
            'name_mm' => 'ဆည်မြောင်း',
            'slug_en' => 'irrigation',
            'slug_mm' => 'ဆည်မြောင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $irrigation->appendToNode($parent)->save();

        $animalFeed = Category::create([
            'name_en' => 'Animal Feed',
            'name_mm' => 'တိရစ္ဆာန်အစာများ',
            'slug_en' => 'animal-feed',
            'slug_mm' => 'တိရစ္ဆာန်အစာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $animalFeed->appendToNode($parent)->save();
    }

    private function createAutomotiveSubCategories(Category $parent)
    {
        $cars = Category::create([
            'name_en' => 'Cars',
            'name_mm' => 'ကားများ',
            'slug_en' => 'cars',
            'slug_mm' => 'ကား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $cars->appendToNode($parent)->save();

        $motorcycles = Category::create([
            'name_en' => 'Motorcycles',
            'name_mm' => 'မော်တော်ဆိုင်ကယ်များ',
            'slug_en' => 'motorcycles',
            'slug_mm' => 'မော်တော်ဆိုင်ကယ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $motorcycles->appendToNode($parent)->save();

        $parts = Category::create([
            'name_en' => 'Parts & Accessories',
            'name_mm' => 'အစိတ်အပိုင်းနှင့် အရန်ပစ္စည်းများ',
            'slug_en' => 'parts-accessories',
            'slug_mm' => 'အစိတ်အပိုင်း-အရန်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $parts->appendToNode($parent)->save();

        $tires = Category::create([
            'name_en' => 'Tires',
            'name_mm' => 'တိုင်းများ',
            'slug_en' => 'tires',
            'slug_mm' => 'တိုင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $tires->appendToNode($parent)->save();

        $tools = Category::create([
            'name_en' => 'Automotive Tools',
            'name_mm' => 'ယာဉ်ပြုပြင်ရေးကိရိယာများ',
            'slug_en' => 'automotive-tools',
            'slug_mm' => 'ယာဉ်ပြုပြင်ရေးကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $tools->appendToNode($parent)->save();

        $lubricants = Category::create([
            'name_en' => 'Lubricants & Oils',
            'name_mm' => 'ဆီနှင့် လူဘြီကင်များ',
            'slug_en' => 'lubricants-oils',
            'slug_mm' => 'ဆီ-လူဘြီကင်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $lubricants->appendToNode($parent)->save();
    }

    private function createBooksSubCategories(Category $parent)
    {
        $books = Category::create([
            'name_en' => 'Books',
            'name_mm' => 'စာအုပ်များ',
            'slug_en' => 'books',
            'slug_mm' => 'စာအုပ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $books->appendToNode($parent)->save();

        $magazines = Category::create([
            'name_en' => 'Magazines',
            'name_mm' => 'မဂ္ဂဇင်းများ',
            'slug_en' => 'magazines',
            'slug_mm' => 'မဂ္ဂဇင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $magazines->appendToNode($parent)->save();

        $stationery = Category::create([
            'name_en' => 'Stationery',
            'name_mm' => 'စာရေးကိရိယာများ',
            'slug_en' => 'stationery',
            'slug_mm' => 'စာရေးကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $stationery->appendToNode($parent)->save();

        $educational = Category::create([
            'name_en' => 'Educational Materials',
            'name_mm' => 'ပညာရေးဆိုင်ရာပစ္စည်းများ',
            'slug_en' => 'educational-materials',
            'slug_mm' => 'ပညာရေးဆိုင်ရာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $educational->appendToNode($parent)->save();
    }

    private function createHealthSubCategories(Category $parent)
    {
        $equipment = Category::create([
            'name_en' => 'Medical Equipment',
            'name_mm' => 'ဆေးဘက်ဆိုင်ရာကိရိယာများ',
            'slug_en' => 'medical-equipment',
            'slug_mm' => 'ဆေးဘက်ဆိုင်ရာကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $equipment->appendToNode($parent)->save();

        $pharmaceuticals = Category::create([
            'name_en' => 'Pharmaceuticals',
            'name_mm' => 'ဆေးဝါးများ',
            'slug_en' => 'pharmaceuticals',
            'slug_mm' => 'ဆေးဝါး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $pharmaceuticals->appendToNode($parent)->save();

        $supplements = Category::create([
            'name_en' => 'Health Supplements',
            'name_mm' => 'ကျန်းမာရေးဖြည့်စွက်စာများ',
            'slug_en' => 'health-supplements',
            'slug_mm' => 'ကျန်းမာရေးဖြည့်စွက်စာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $supplements->appendToNode($parent)->save();

        $supplies = Category::create([
            'name_en' => 'Medical Supplies',
            'name_mm' => 'ဆေးဘက်ဆိုင်ရာပစ္စည်းများ',
            'slug_en' => 'medical-supplies',
            'slug_mm' => 'ဆေးဘက်ဆိုင်ရာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $supplies->appendToNode($parent)->save();
    }

    private function createPetsSubCategories(Category $parent)
    {
        $dog = Category::create([
            'name_en' => 'Dog Supplies',
            'name_mm' => 'ခွေးအတွက်ပစ္စည်းများ',
            'slug_en' => 'dog-supplies',
            'slug_mm' => 'ခွေးအတွက်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $dog->appendToNode($parent)->save();

        $cat = Category::create([
            'name_en' => 'Cat Supplies',
            'name_mm' => 'ကြောင်အတွက်ပစ္စည်းများ',
            'slug_en' => 'cat-supplies',
            'slug_mm' => 'ကြောင်အတွက်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $cat->appendToNode($parent)->save();

        $bird = Category::create([
            'name_en' => 'Bird Supplies',
            'name_mm' => 'ငှက်အတွက်ပစ္စည်းများ',
            'slug_en' => 'bird-supplies',
            'slug_mm' => 'ငှက်အတွက်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $bird->appendToNode($parent)->save();

        $food = Category::create([
            'name_en' => 'Pet Food',
            'name_mm' => 'အိမ်မွေးတိရစ္ဆာန်အစာများ',
            'slug_en' => 'pet-food',
            'slug_mm' => 'အိမ်မွေးတိရစ္ဆာန်အစာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $food->appendToNode($parent)->save();
    }

    private function createOfficeSubCategories(Category $parent)
    {
        $furniture = Category::create([
            'name_en' => 'Office Furniture',
            'name_mm' => 'ရုံးသုံးပရိဘောဂ',
            'slug_en' => 'office-furniture',
            'slug_mm' => 'ရုံးသုံးပရိဘောဂ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $furniture->appendToNode($parent)->save();

        $electronics = Category::create([
            'name_en' => 'Office Electronics',
            'name_mm' => 'ရုံးသုံးအီလက်ထရောနစ်',
            'slug_en' => 'office-electronics',
            'slug_mm' => 'ရုံးသုံးအီလက်ထရောနစ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $electronics->appendToNode($parent)->save();

        $stationery = Category::create([
            'name_en' => 'Office Stationery',
            'name_mm' => 'ရုံးသုံးစာရေးကိရိယာများ',
            'slug_en' => 'office-stationery',
            'slug_mm' => 'ရုံးသုံးစာရေးကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $stationery->appendToNode($parent)->save();

        $cleaning = Category::create([
            'name_en' => 'Cleaning Supplies',
            'name_mm' => 'သန့်ရှင်းရေးပစ္စည်းများ',
            'slug_en' => 'cleaning-supplies',
            'slug_mm' => 'သန့်ရှင်းရေးပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $cleaning->appendToNode($parent)->save();
    }

    private function createToysSubCategories(Category $parent)
    {
        $educational = Category::create([
            'name_en' => 'Educational Toys',
            'name_mm' => 'ပညာရေးကစားစရာများ',
            'slug_en' => 'educational-toys',
            'slug_mm' => 'ပညာရေးကစားစရာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $educational->appendToNode($parent)->save();

        $outdoor = Category::create([
            'name_en' => 'Outdoor Toys',
            'name_mm' => 'အပြင်ပန်းကစားစရာများ',
            'slug_en' => 'outdoor-toys',
            'slug_mm' => 'အပြင်ပန်းကစားစရာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $outdoor->appendToNode($parent)->save();

        $boardGames = Category::create([
            'name_en' => 'Board Games',
            'name_mm' => 'ဘုတ်ဂိမ်းများ',
            'slug_en' => 'board-games',
            'slug_mm' => 'ဘုတ်ဂိမ်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $boardGames->appendToNode($parent)->save();

        $dolls = Category::create([
            'name_en' => 'Dolls & Action Figures',
            'name_mm' => 'အရုပ်များနှင့် လှုပ်ရှားပုံများ',
            'slug_en' => 'dolls-action-figures',
            'slug_mm' => 'အရုပ်-လှုပ်ရှားပုံ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $dolls->appendToNode($parent)->save();
    }

    private function createJewelrySubCategories(Category $parent)
    {
        $gold = Category::create([
            'name_en' => 'Gold Jewelry',
            'name_mm' => 'ရွှေလက်ဝတ်လက်စား',
            'slug_en' => 'gold-jewelry',
            'slug_mm' => 'ရွှေလက်ဝတ်လက်စား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $gold->appendToNode($parent)->save();

        $silver = Category::create([
            'name_en' => 'Silver Jewelry',
            'name_mm' => 'ငွေလက်ဝတ်လက်စား',
            'slug_en' => 'silver-jewelry',
            'slug_mm' => 'ငွေလက်ဝတ်လက်စား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $silver->appendToNode($parent)->save();

        $watches = Category::create([
            'name_en' => 'Watches',
            'name_mm' => 'နာရီများ',
            'slug_en' => 'watches',
            'slug_mm' => 'နာရီ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $watches->appendToNode($parent)->save();

        $gemstones = Category::create([
            'name_en' => 'Gemstones',
            'name_mm' => 'ရတနာများ',
            'slug_en' => 'gemstones',
            'slug_mm' => 'ရတနာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $gemstones->appendToNode($parent)->save();
    }

    private function createMusicalSubCategories(Category $parent)
    {
        $guitars = Category::create([
            'name_en' => 'Guitars',
            'name_mm' => 'ဂီတာများ',
            'slug_en' => 'guitars',
            'slug_mm' => 'ဂီတာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $guitars->appendToNode($parent)->save();

        $pianos = Category::create([
            'name_en' => 'Pianos & Keyboards',
            'name_mm' => 'ပီယာနိုနှင့် ကီးဘုတ်များ',
            'slug_en' => 'pianos-keyboards',
            'slug_mm' => 'ပီယာနို-ကီးဘုတ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $pianos->appendToNode($parent)->save();

        $drums = Category::create([
            'name_en' => 'Drums & Percussion',
            'name_mm' => 'စည်တူရိယာများနှင့် ရိုက်ခတ်ကိရိယာများ',
            'slug_en' => 'drums-percussion',
            'slug_mm' => 'စည်-ရိုက်ခတ်ကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $drums->appendToNode($parent)->save();

        $audioEquipment = Category::create([
            'name_en' => 'Audio Equipment',
            'name_mm' => 'အသံထုတ်လုပ်ရေးကိရိယာများ',
            'slug_en' => 'audio-equipment',
            'slug_mm' => 'အသံထုတ်လုပ်ရေးကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $audioEquipment->appendToNode($parent)->save();
    }

    private function createArtSubCategories(Category $parent)
    {
        $paintings = Category::create([
            'name_en' => 'Paintings',
            'name_mm' => 'ပန်းချီများ',
            'slug_en' => 'paintings',
            'slug_mm' => 'ပန်းချီ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $paintings->appendToNode($parent)->save();

        $craftMaterials = Category::create([
            'name_en' => 'Craft Materials',
            'name_mm' => 'လက်မှုပစ္စည်းများ',
            'slug_en' => 'craft-materials',
            'slug_mm' => 'လက်မှုပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $craftMaterials->appendToNode($parent)->save();

        $sculptures = Category::create([
            'name_en' => 'Sculptures',
            'name_mm' => 'ရုပ်ထုများ',
            'slug_en' => 'sculptures',
            'slug_mm' => 'ရုပ်ထု',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $sculptures->appendToNode($parent)->save();

        $artSupplies = Category::create([
            'name_en' => 'Art Supplies',
            'name_mm' => 'အနုပညာပစ္စည်းများ',
            'slug_en' => 'art-supplies',
            'slug_mm' => 'အနုပညာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $artSupplies->appendToNode($parent)->save();
    }

    private function createGardenSubCategories(Category $parent)
    {
        $gardeningTools = Category::create([
            'name_en' => 'Gardening Tools',
            'name_mm' => 'ဥယျာဉ်ခြံကိရိယာများ',
            'slug_en' => 'gardening-tools',
            'slug_mm' => 'ဥယျာဉ်ခြံကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $gardeningTools->appendToNode($parent)->save();

        $outdoorFurniture = Category::create([
            'name_en' => 'Outdoor Furniture',
            'name_mm' => 'အပြင်ပန်းပရိဘောဂ',
            'slug_en' => 'outdoor-furniture',
            'slug_mm' => 'အပြင်ပန်းပရိဘောဂ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $outdoorFurniture->appendToNode($parent)->save();

        $plants = Category::create([
            'name_en' => 'Plants & Seeds',
            'name_mm' => 'အပင်များနှင့် မျိုးစေ့များ',
            'slug_en' => 'plants-seeds',
            'slug_mm' => 'အပင်-မျိုးစေ့',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $plants->appendToNode($parent)->save();

        $decor = Category::create([
            'name_en' => 'Garden Decor',
            'name_mm' => 'ဥယျာဉ်အလှဆင်ပစ္စည်း',
            'slug_en' => 'garden-decor',
            'slug_mm' => 'ဥယျာဉ်အလှဆင်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $decor->appendToNode($parent)->save();
    }

    private function createBabySubCategories(Category $parent)
    {
        $clothing = Category::create([
            'name_en' => 'Baby Clothing',
            'name_mm' => 'ကလေးအဝတ်အစား',
            'slug_en' => 'baby-clothing',
            'slug_mm' => 'ကလေးအဝတ်အစား',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $clothing->appendToNode($parent)->save();

        $toys = Category::create([
            'name_en' => 'Baby Toys',
            'name_mm' => 'ကလေးကစားစရာ',
            'slug_en' => 'baby-toys',
            'slug_mm' => 'ကလေးကစားစရာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $toys->appendToNode($parent)->save();

        $feeding = Category::create([
            'name_en' => 'Feeding Supplies',
            'name_mm' => 'နို့တိုက်ရေးပစ္စည်း',
            'slug_en' => 'feeding-supplies',
            'slug_mm' => 'နို့တိုက်ရေးပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $feeding->appendToNode($parent)->save();

        $safety = Category::create([
            'name_en' => 'Baby Safety',
            'name_mm' => 'ကလေးလုံခြုံရေး',
            'slug_en' => 'baby-safety',
            'slug_mm' => 'ကလေးလုံခြုံရေး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $safety->appendToNode($parent)->save();
    }

    private function createTravelSubCategories(Category $parent)
    {
        $luggage = Category::create([
            'name_en' => 'Luggage',
            'name_mm' => 'ခရီးဆောင်အိတ်များ',
            'slug_en' => 'luggage',
            'slug_mm' => 'ခရီးဆောင်အိတ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $luggage->appendToNode($parent)->save();

        $bags = Category::create([
            'name_en' => 'Travel Bags',
            'name_mm' => 'ခရီးသွားအိတ်များ',
            'slug_en' => 'travel-bags',
            'slug_mm' => 'ခရီးသွားအိတ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $bags->appendToNode($parent)->save();

        $accessories = Category::create([
            'name_en' => 'Travel Accessories',
            'name_mm' => 'ခရီးသွားအရန်ပစ္စည်း',
            'slug_en' => 'travel-accessories',
            'slug_mm' => 'ခရီးသွားအရန်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $accessories->appendToNode($parent)->save();

        $comfort = Category::create([
            'name_en' => 'Travel Comfort',
            'name_mm' => 'ခရီးသွားအဆင်ပြေရေး',
            'slug_en' => 'travel-comfort',
            'slug_mm' => 'ခရီးသွားအဆင်ပြေရေး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $comfort->appendToNode($parent)->save();
    }

    private function createPartySubCategories(Category $parent)
    {
        $decorations = Category::create([
            'name_en' => 'Decorations',
            'name_mm' => 'အလှဆင်ပစ္စည်း',
            'slug_en' => 'decorations',
            'slug_mm' => 'အလှဆင်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $decorations->appendToNode($parent)->save();

        $tableware = Category::create([
            'name_en' => 'Tableware',
            'name_mm' => 'စားပွဲတင်ပစ္စည်း',
            'slug_en' => 'tableware',
            'slug_mm' => 'စားပွဲတင်ပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $tableware->appendToNode($parent)->save();

        $balloons = Category::create([
            'name_en' => 'Balloons',
            'name_mm' => 'ပူဖောင်းများ',
            'slug_en' => 'balloons',
            'slug_mm' => 'ပူဖောင်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $balloons->appendToNode($parent)->save();

        $lights = Category::create([
            'name_en' => 'Party Lights',
            'name_mm' => 'ပါတီမီးများ',
            'slug_en' => 'party-lights',
            'slug_mm' => 'ပါတီမီး',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $lights->appendToNode($parent)->save();
    }

    private function createReligiousSubCategories(Category $parent)
    {
        $buddhist = Category::create([
            'name_en' => 'Buddhist Items',
            'name_mm' => 'ဗုဒ္ဓဘာသာပစ္စည်း',
            'slug_en' => 'buddhist-items',
            'slug_mm' => 'ဗုဒ္ဓဘာသာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $buddhist->appendToNode($parent)->save();

        $christian = Category::create([
            'name_en' => 'Christian Items',
            'name_mm' => 'ခရစ်ယာန်ဘာသာပစ္စည်း',
            'slug_en' => 'christian-items',
            'slug_mm' => 'ခရစ်ယာန်ဘာသာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $christian->appendToNode($parent)->save();

        $muslim = Category::create([
            'name_en' => 'Muslim Items',
            'name_mm' => 'မွတ်စလင်ဘာသာပစ္စည်း',
            'slug_en' => 'muslim-items',
            'slug_mm' => 'မွတ်စလင်ဘာသာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $muslim->appendToNode($parent)->save();

        $hindu = Category::create([
            'name_en' => 'Hindu Items',
            'name_mm' => 'ဟိန္ဒူဘာသာပစ္စည်း',
            'slug_en' => 'hindu-items',
            'slug_mm' => 'ဟိန္ဒူဘာသာပစ္စည်း',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $hindu->appendToNode($parent)->save();
    }

    private function createDigitalSubCategories(Category $parent)
    {
        $software = Category::create([
            'name_en' => 'Software',
            'name_mm' => 'ဆော့ဖ်ဝဲ',
            'slug_en' => 'software',
            'slug_mm' => 'ဆော့ဖ်ဝဲ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $software->appendToNode($parent)->save();

        $ebooks = Category::create([
            'name_en' => 'E-books',
            'name_mm' => 'အီးစာအုပ်',
            'slug_en' => 'e-books',
            'slug_mm' => 'အီးစာအုပ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $ebooks->appendToNode($parent)->save();

        $digitalArt = Category::create([
            'name_en' => 'Digital Art',
            'name_mm' => 'ဒစ်ဂျစ်တယ်အနုပညာ',
            'slug_en' => 'digital-art',
            'slug_mm' => 'ဒစ်ဂျစ်တယ်အနုပညာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $digitalArt->appendToNode($parent)->save();

        $templates = Category::create([
            'name_en' => 'Templates',
            'name_mm' => 'တမ်းပလိတ်',
            'slug_en' => 'templates',
            'slug_mm' => 'တမ်းပလိတ်',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $templates->appendToNode($parent)->save();
    }
}
