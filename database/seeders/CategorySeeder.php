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
            'commission_rate' => 0.15
        ]);

        $phones = Category::create([
            'name' => 'Smartphones',
            'name_mm' => 'စမတ်ဖုန်းများ',
            'slug' => 'smartphones',
            'commission_rate' => 0.12,
            'parent_id' => $electronics->id
        ]);

        $laptops = Category::create([
            'name' => 'Laptops',
            'name_mm' => 'လက်ပ်တော့များ',
            'slug' => 'laptops',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id
        ]);

        $tablets = Category::create([
            'name' => 'Tablets',
            'name_mm' => 'တက်ဘလက်များ',
            'slug' => 'tablets',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id
        ]);

        // Fashion Category Tree
        $fashion = Category::create([
            'name' => 'Fashion',
            'name_mm' => 'ဖက်ရှင်',
            'slug' => 'fashion',
            'commission_rate' => 0.08
        ]);

        $mensClothing = Category::create([
            'name' => "Men's Clothing",
            'name_mm' => 'ယောက်ျားဝတ်စုံ',
            'slug' => 'mens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id
        ]);

        $womensClothing = Category::create([
            'name' => "Women's Clothing",
            'name_mm' => 'မိန်းမဝတ်စုံ',
            'slug' => 'womens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id
        ]);

        // Home & Kitchen Category Tree
        $homeKitchen = Category::create([
            'name' => 'Home & Kitchen',
            'name_mm' => 'အိမ်သုံးပစ္စည်းများ',
            'slug' => 'home-kitchen',
            'commission_rate' => 0.05
        ]);

        $furniture = Category::create([
            'name' => 'Furniture',
            'name_mm' => 'ပရိဘောဂ',
            'slug' => 'furniture',
            'commission_rate' => 0.06,
            'parent_id' => $homeKitchen->id
        ]);

        $kitchenAppliances = Category::create([
            'name' => 'Kitchen Appliances',
            'name_mm' => 'မီးဖိုချောင်သုံးပစ္စည်းများ',
            'slug' => 'kitchen-appliances',
            'commission_rate' => 0.05,
            'parent_id' => $homeKitchen->id
        ]);

        // Office Supplies Category
        $officeSupplies = Category::create([
            'name' => 'Office Supplies',
            'name_mm' => 'ရုံးသုံးပစ္စည်းများ',
            'slug' => 'office-supplies',
            'commission_rate' => 0.04
        ]);
    }
}