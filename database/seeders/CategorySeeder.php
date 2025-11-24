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

        $laptops = Category::create([
            'name' => 'Accessories',
            'slug' => 'accessories',
            'commission_rate' => 0.10,
            'parent_id' => $electronics->id,
            'image' => 'https://picsum.photos/seed/airpods/600/400'
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

        // Food & Beverages Category
        $food = Category::create([
            'name' => 'Food & Beverages',
            'slug' => 'food-beverages',
            'commission_rate' => 0.05,
            'image' => 'https://picsum.photos/seed/food-beverages/600/400'
        ]);
        
        $grains = Category::create([
            'name' => 'Grains & Rice',
            'slug' => 'grains-rice',
            'commission_rate' => 0.05,
            'parent_id' => $food->id,
            'image' => 'https://picsum.photos/seed/grains-rice/600/400'
        ]);
        
        $snacks = Category::create([
            'name' => 'Snacks',
            'slug' => 'snacks',
            'commission_rate' => 0.06,
            'parent_id' => $food->id,
            'image' => 'https://picsum.photos/seed/snacks/600/400'
        ]);
        
        // Beauty & Personal Care Category
        $beauty = Category::create([
            'name' => 'Beauty & Personal Care',
            'slug' => 'beauty-personal-care',
            'commission_rate' => 0.08,
            'image' => 'https://picsum.photos/seed/beauty/600/400'
        ]);
        
        $skincare = Category::create([
            'name' => 'Skincare',
            'slug' => 'skincare',
            'commission_rate' => 0.07,
            'parent_id' => $beauty->id,
            'image' => 'https://picsum.photos/seed/skincare/600/400'
        ]);
        
        $haircare = Category::create([
            'name' => 'Haircare',
            'slug' => 'haircare',
            'commission_rate' => 0.07,
            'parent_id' => $beauty->id,
            'image' => 'https://picsum.photos/seed/haircare/600/400'
        ]);
        
        // Sports & Outdoors Category
        $sports = Category::create([
            'name' => 'Sports & Outdoors',
            'slug' => 'sports-outdoors',
            'commission_rate' => 0.05,
            'image' => 'https://picsum.photos/seed/sports/600/400'
        ]);
        
        $fitness = Category::create([
            'name' => 'Fitness Equipment',
            'slug' => 'fitness-equipment',
            'commission_rate' => 0.05,
            'parent_id' => $sports->id,
            'image' => 'https://picsum.photos/seed/fitness/600/400'
        ]);
        
        $camping = Category::create([
            'name' => 'Camping & Hiking',
            'slug' => 'camping-hiking',
            'commission_rate' => 0.06,
            'parent_id' => $sports->id,
            'image' => 'https://picsum.photos/seed/camping/600/400'
        ]);
        
        // Books Category
        $books = Category::create([
            'name' => 'Books',
            'slug' => 'books',
            'commission_rate' => 0.03,
            'image' => 'https://picsum.photos/seed/books/600/400'
        ]);

    }
}