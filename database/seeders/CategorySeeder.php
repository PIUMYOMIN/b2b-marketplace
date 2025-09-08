<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        // Electronics Category Tree
        $electronics = Category::create([
            'name' => 'Electronics',
            'name_mm' => 'အီလက်ထရောနစ်',
            'slug' => 'electronics',
            'commission_rate' => 0.15,
            'image' => 'https://picsum.photos/seed/electronics/600/400'
        ]);

        $phones = Category::create([
            'name' => 'Smartphones',
            'name_mm' => 'စမတ်ဖုန်းများ',
            'slug' => 'smartphones',
            'commission_rate' => 0.12,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/smartphones/600/400'
        ]);

        $laptops = Category::create([
            'name' => 'Laptops',
            'name_mm' => 'လက်ပ်တော့များ',
            'slug' => 'laptops',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/laptops/600/400'
        ]);

        $tablets = Category::create([
            'name' => 'Tablets',
            'name_mm' => 'တက်ဘလက်များ',
            'slug' => 'tablets',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/tablets/600/400'
        ]);

        // Fashion Category Tree
        $fashion = Category::create([
            'name' => 'Fashion',
            'name_mm' => 'ဖက်ရှင်',
            'slug' => 'fashion',
            'commission_rate' => 0.08,
            'image' => 'https://picsum.photos/seed/fashion/600/400'
        ]);

        $mensClothing = Category::create([
            'name' => "Men's Clothing",
            'name_mm' => 'ယောက်ျားဝတ်စုံ',
            'slug' => 'mens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id,
            'image' => 'https://picsum.photos/seed/mens-clothing/600/400'
        ]);

        $womensClothing = Category::create([
            'name' => "Women's Clothing",
            'name_mm' => 'မိန်းမဝတ်စုံ',
            'slug' => 'womens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id,
            'image' => 'https://picsum.photos/seed/womens-clothing/600/400'
        ]);

        // Home & Kitchen Category Tree
        $homeKitchen = Category::create([
            'name' => 'Home & Kitchen',
            'name_mm' => 'အိမ်သုံးပစ္စည်းများ',
            'slug' => 'home-kitchen',
            'commission_rate' => 0.05,
            'image' => 'https://picsum.photos/seed/home-kitchen/600/400'
        ]);

        $furniture = Category::create([
            'name' => 'Furniture',
            'name_mm' => 'ပရိဘောဂ',
            'slug' => 'furniture',
            'commission_rate' => 0.06,
            'parent_id' => $homeKitchen->id,
            'image' => 'https://picsum.photos/seed/furniture/600/400'
        ]);

        $kitchenAppliances = Category::create([
            'name' => 'Kitchen Appliances',
            'name_mm' => 'မီးဖိုချောင်သုံးပစ္စည်းများ',
            'slug' => 'kitchen-appliances',
            'commission_rate' => 0.05,
            'parent_id' => $homeKitchen->id,
            'image' => 'https://picsum.photos/seed/kitchen-appliances/600/400'
        ]);

        // Office Supplies Category
        $officeSupplies = Category::create([
            'name' => 'Office Supplies',
            'name_mm' => 'ရုံးသုံးပစ္စည်းများ',
            'slug' => 'office-supplies',
            'commission_rate' => 0.04,
            'image' => 'https://picsum.photos/seed/office-supplies/600/400'
        ]);
    }
}