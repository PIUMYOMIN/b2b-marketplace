<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Electronics
        $electronics = Category::create([
            'name' => 'Electronics',
            'name_mm' => 'အီလက်ထရောနစ်ပစ္စည်းများ',
            'slug' => 'electronics',
            'commission_rate' => 0.15,
            'image' => 'https://picsum.photos/seed/electronics/600/400',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Smartphones',
            'name_mm' => 'မိုဘိုင်းဖုန်းများ',
            'slug' => 'smartphones',
            'commission_rate' => 0.12,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/smartphones/600/400',
        ]);

        Category::create([
            'name' => 'Laptops',
            'name_mm' => 'လက်တော့ပ်များ',
            'slug' => 'laptops',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/laptops/600/400',
        ]);

        Category::create([
            'name' => 'Accessories',
            'name_mm' => 'ဆက်စပ်ပစ္စည်းများ',
            'slug' => 'accessories',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/accessories/600/400',
        ]);

        // Fashion
        $fashion = Category::create([
            'name' => 'Fashion',
            'name_mm' => 'ဖက်ရှင်',
            'slug' => 'fashion',
            'commission_rate' => 0.08,
            'image' => 'https://picsum.photos/seed/fashion/600/400',
        ]);

        Category::create([
            'name' => "Men's Clothing",
            'name_mm' => 'အမျိုးသားအဝတ်အစားများ',
            'slug' => 'mens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id,
            'image' => 'https://picsum.photos/seed/mens-clothing/600/400',
        ]);

        Category::create([
            'name' => "Women's Clothing",
            'name_mm' => 'အမျိုးသမီးအဝတ်အစားများ',
            'slug' => 'womens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id,
            'image' => 'https://picsum.photos/seed/womens-clothing/600/400',
        ]);

        // Home & Kitchen
        $homeKitchen = Category::create([
            'name' => 'Home & Kitchen',
            'name_mm' => 'အိမ်သုံးပစ္စည်းများ',
            'slug' => 'home-kitchen',
            'commission_rate' => 0.05,
            'image' => 'https://picsum.photos/seed/home-kitchen/600/400',
        ]);

        Category::create([
            'name' => 'Furniture',
            'name_mm' => 'ပရိဘောဂများ',
            'slug' => 'furniture',
            'commission_rate' => 0.06,
            'parent_id' => $homeKitchen->id,
            'image' => 'https://picsum.photos/seed/furniture/600/400',
        ]);

        Category::create([
            'name' => 'Kitchen Appliances',
            'name_mm' => 'မီးဖိုချောင်သုံးစက်ပစ္စည်းများ',
            'slug' => 'kitchen-appliances',
            'commission_rate' => 0.05,
            'parent_id' => $homeKitchen->id,
            'image' => 'https://picsum.photos/seed/kitchen-appliances/600/400',
        ]);
    }
}