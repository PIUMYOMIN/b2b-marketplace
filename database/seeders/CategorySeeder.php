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

        // Now create sub-categories
        $this->createElectronicsSubCategories($electronics);
        $this->createFashionSubCategories($fashion);
        $this->createHomeKitchenSubCategories($homeKitchen);
        $this->createFoodSubCategories($food);
        $this->createBeautySubCategories($beauty);
        $this->createSportsSubCategories($sports);
        $this->createIndustrialSubCategories($industrial);
        $this->createAgricultureSubCategories($agriculture);

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

        $jewelry = Category::create([
            'name_en' => 'Jewelry & Watches',
            'name_mm' => 'လက်ဝတ်လက်စားနှင့် လက်ပတ်နာရီများ',
            'slug_en' => 'jewelry-watches',
            'slug_mm' => 'လက်ဝတ်လက်စား-လက်ပတ်နာရီ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $jewelry->appendToNode($parent)->save();
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

        $supplements = Category::create([
            'name_en' => 'Health Supplements',
            'name_mm' => 'ကျန်းမာရေးဖြည့်စွက်စာများ',
            'slug_en' => 'health-supplements',
            'slug_mm' => 'ကျန်းမာရေးဖြည့်စွက်စာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $supplements->appendToNode($parent)->save();
    }

    private function createSportsSubCategories(Category $parent)
    {
        $fitness = Category::create([
            'name_en' => 'Fitness Equipment',
            'name_mm' => 'ကျန်းမာရေးကိရိယာများ',
            'slug_en' => 'fitness-equipment',
            'slug_mm' => 'ကျန်းမာရေးကိရိယာ',
            'commission_rate' => $parent->commission_rate + 0.02,
            'is_active' => true,
        ]);
        $fitness->appendToNode($parent)->save();

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
}
