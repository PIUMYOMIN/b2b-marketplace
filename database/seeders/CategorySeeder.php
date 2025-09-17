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
            'slug' => 'electronics',
            'commission_rate' => 0.15,
            'image' => 'https://picsum.photos/seed/electronics/600/400'
        ]);

        $phones = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'commission_rate' => 0.12,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/smartphones/600/400'
        ]);

        $laptops = Category::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/laptops/600/400'
        ]);

        $tablets = Category::create([
            'name' => 'Tablets',
            'slug' => 'tablets',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/tablets/600/400'
        ]);

        // Fashion Category Tree
        $fashion = Category::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
            'commission_rate' => 0.08,
            'image' => 'https://picsum.photos/seed/fashion/600/400'
        ]);

        $mensClothing = Category::create([
            'name' => "Men's Clothing",
            'slug' => 'mens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id,
            'image' => 'https://picsum.photos/seed/mens-clothing/600/400'
        ]);

        $womensClothing = Category::create([
            'name' => "Women's Clothing",
            'slug' => 'womens-clothing',
            'commission_rate' => 0.07,
            'parent_id' => $fashion->id,
            'image' => 'https://picsum.photos/seed/womens-clothing/600/400'
        ]);

        // Home & Kitchen Category Tree
        $homeKitchen = Category::create([
            'name' => 'Home & Kitchen',
            'slug' => 'home-kitchen',
            'commission_rate' => 0.05,
            'image' => 'https://picsum.photos/seed/home-kitchen/600/400'
        ]);

        $furniture = Category::create([
            'name' => 'Furniture',
            'slug' => 'furniture',
            'commission_rate' => 0.06,
            'parent_id' => $homeKitchen->id,
            'image' => 'https://picsum.photos/seed/furniture/600/400'
        ]);

        $kitchenAppliances = Category::create([
            'name' => 'Kitchen Appliances',
            'slug' => 'kitchen-appliances',
            'commission_rate' => 0.05,
            'parent_id' => $homeKitchen->id,
            'image' => 'https://picsum.photos/seed/kitchen-appliances/600/400'
        ]);

        // Office Supplies Category
        $officeSupplies = Category::create([
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
            'commission_rate' => 0.04,
            'image' => 'https://picsum.photos/seed/office-supplies/600/400'
        ]);
    }
}