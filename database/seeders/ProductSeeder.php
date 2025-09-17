<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get seller ID
        $sellerId = User::whereHas('roles', function ($query) {
            $query->where('name', 'seller');
        })->first()->id;

        // Get categories by slug
        $foodCategory       = Category::where('slug', 'kitchen-appliances')->first()->id; // for rice
        $homeCategory       = Category::where('slug', 'furniture')->first()->id; // for bamboo basket
        $fashionCategory    = Category::where('slug', 'womens-clothing')->first()->id; // for Shan bag
        $electronicsCategory = Category::where('slug', 'smartphones')->first()->id; // example for phones
        $riceCategory     = Category::where('slug', 'grains-rice')->first()->id;
        $snacksCategory   = Category::where('slug', 'snacks')->first()->id;
        $skincareCategory = Category::where('slug', 'skincare')->first()->id;
        $haircareCategory = Category::where('slug', 'haircare')->first()->id;
        $fitnessCategory  = Category::where('slug', 'fitness-equipment')->first()->id;
        $campingCategory  = Category::where('slug', 'camping-hiking')->first()->id;
        $booksCategory    = Category::where('slug', 'books')->first()->id;

        $products = [
            [
                'name' => 'Organic Rice',
                'description' => 'High-quality organic rice grown in Myanmar with sustainable farming practices. Perfect for daily consumption and special occasions.',
                'price' => 45000,
                'quantity' => 100,
                'category_id' => $foodCategory, // Kitchen Appliances
                'seller_id' => $sellerId,
                'average_rating' => 4.5,
                'review_count' => 28,
                'specifications' => json_encode([
                    'weight' => '5kg',
                    'origin' => 'Mandalay Region',
                    'certification' => 'Organic Certified',
                    'shelf_life' => '12 months',
                    'cooking_time' => '15-20 minutes',
                    'grain_type' => 'Long grain'
                ]),
                'images' => json_encode([
                    [
                        'url' => 'https://images.pexels.com/photos/4110256/pexels-photo-4110256.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'front',
                        'is_primary' => true,
                        'order' => 0
                    ],
                    [
                        'url' => 'https://images.pexels.com/photos/1233528/pexels-photo-1233528.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'package',
                        'is_primary' => false,
                        'order' => 1
                    ],
                    [
                        'url' => 'https://images.pexels.com/photos/4109724/pexels-photo-4109724.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'cooked',
                        'is_primary' => false,
                        'order' => 2
                    ]
                ]),
                'moq' => 1,
                'lead_time' => '2-3 days',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Handwoven Bamboo Basket',
                'description' => 'Beautiful handwoven bamboo basket made by local artisans. Perfect for storage and decorative purposes.',
                'price' => 15000,
                'quantity' => 50,
                'category_id' => $homeCategory, // Furniture
                'seller_id' => $sellerId,
                'average_rating' => 4.8,
                'review_count' => 15,
                'specifications' => json_encode([
                    'material' => 'Natural bamboo',
                    'dimensions' => '30cm x 30cm x 20cm',
                    'weight' => '0.5kg',
                    'origin' => 'Sagaing Region',
                    'craftsmanship' => 'Handwoven',
                    'color' => 'Natural bamboo color'
                ]),
                'images' => json_encode([
                    [
                        'url' => 'https://images.pexels.com/photos/7766569/pexels-photo-7766569.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'front',
                        'is_primary' => true,
                        'order' => 0
                    ],
                    [
                        'url' => 'https://images.pexels.com/photos/7766568/pexels-photo-7766568.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'side',
                        'is_primary' => false,
                        'order' => 1
                    ],
                    [
                        'url' => 'https://images.pexels.com/photos/7766570/pexels-photo-7766570.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'top',
                        'is_primary' => false,
                        'order' => 2
                    ]
                ]),
                'moq' => 1,
                'lead_time' => '3-5 days',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Traditional Shan Bag',
                'description' => 'Colorful traditional Shan bag made with handwoven fabric. Features intricate patterns and durable construction.',
                'price' => 25000,
                'quantity' => 30,
                'category_id' => $fashionCategory, // Women's Clothing
                'seller_id' => $sellerId,
                'average_rating' => 4.7,
                'review_count' => 22,
                'specifications' => json_encode([
                    'material' => 'Cotton blend',
                    'dimensions' => '35cm x 40cm',
                    'strap_length' => 'Adjustable up to 120cm',
                    'origin' => 'Shan State',
                    'closure' => 'Zipper',
                    'pockets' => '2 main compartments, 1 front pocket'
                ]),
                'images' => json_encode([
                    [
                        'url' => 'https://images.pexels.com/photos/1152077/pexels-photo-1152077.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'front',
                        'is_primary' => true,
                        'order' => 0
                    ],
                    [
                        'url' => 'https://images.pexels.com/photos/1152078/pexels-photo-1152078.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'back',
                        'is_primary' => false,
                        'order' => 1
                    ],
                    [
                        'url' => 'https://images.pexels.com/photos/1152081/pexels-photo-1152081.jpeg?auto=compress&cs=tinysrgb&w=600',
                        'angle' => 'side',
                        'is_primary' => false,
                        'order' => 2
                    ]
                ]),
                'moq' => 1,
                'lead_time' => '4-6 days',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
        'name' => 'Premium Jasmine Rice',
        'description' => 'Fragrant jasmine rice imported from Thailand, soft and fluffy texture.',
        'price' => 38000,
        'quantity' => 200,
        'category_id' => $riceCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.6,
        'review_count' => 34,
        'specifications' => json_encode([
            'weight' => '5kg',
            'origin' => 'Thailand',
            'shelf_life' => '12 months',
            'grain_type' => 'Jasmine'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/4110256/pexels-photo-4110256.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 1,
        'lead_time' => '3-4 days',
        'is_active' => true,
    ],
    [
        'name' => 'Potato Chips Pack',
        'description' => 'Crunchy salted potato chips, perfect for snacks and parties.',
        'price' => 1500,
        'quantity' => 500,
        'category_id' => $snacksCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.2,
        'review_count' => 55,
        'specifications' => json_encode([
            'weight' => '150g',
            'flavor' => 'Salted',
            'shelf_life' => '6 months'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/4110544/pexels-photo-4110544.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 5,
        'lead_time' => '1-2 days',
        'is_active' => true,
    ],
    [
        'name' => 'Herbal Face Cream',
        'description' => 'Natural herbal skincare cream that hydrates and refreshes your skin.',
        'price' => 12000,
        'quantity' => 100,
        'category_id' => $skincareCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.9,
        'review_count' => 78,
        'specifications' => json_encode([
            'volume' => '100ml',
            'ingredients' => 'Aloe Vera, Green Tea, Vitamin E',
            'skin_type' => 'All skin types'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/3735619/pexels-photo-3735619.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 2,
        'lead_time' => '2-3 days',
        'is_active' => true,
    ],
    [
        'name' => 'Shampoo with Natural Extracts',
        'description' => 'Mild shampoo enriched with herbal extracts for strong and shiny hair.',
        'price' => 8000,
        'quantity' => 80,
        'category_id' => $haircareCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.4,
        'review_count' => 40,
        'specifications' => json_encode([
            'volume' => '250ml',
            'suitable_for' => 'All hair types',
            'ingredients' => 'Coconut oil, Hibiscus extract'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/3735636/pexels-photo-3735636.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 1,
        'lead_time' => '3-4 days',
        'is_active' => true,
    ],
    [
        'name' => 'Dumbbell Set 10kg',
        'description' => 'Adjustable dumbbell set for home workouts and fitness training.',
        'price' => 45000,
        'quantity' => 40,
        'category_id' => $fitnessCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.8,
        'review_count' => 60,
        'specifications' => json_encode([
            'weight' => '10kg',
            'material' => 'Cast Iron',
            'set' => '2 dumbbells'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/2261485/pexels-photo-2261485.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 1,
        'lead_time' => '5-7 days',
        'is_active' => true,
    ],
    [
        'name' => 'Camping Tent (4 Person)',
        'description' => 'Durable waterproof tent ideal for camping and outdoor adventures.',
        'price' => 85000,
        'quantity' => 25,
        'category_id' => $campingCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.7,
        'review_count' => 18,
        'specifications' => json_encode([
            'capacity' => '4 persons',
            'material' => 'Polyester, Aluminum poles',
            'weight' => '4.5kg'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/1687845/pexels-photo-1687845.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 1,
        'lead_time' => '7-10 days',
        'is_active' => true,
    ],
    [
        'name' => 'Book: The Art of Mindfulness',
        'description' => 'Inspirational book on practicing mindfulness and meditation techniques.',
        'price' => 9000,
        'quantity' => 70,
        'category_id' => $booksCategory,
        'seller_id' => $sellerId,
        'average_rating' => 4.9,
        'review_count' => 110,
        'specifications' => json_encode([
            'author' => 'John Smith',
            'pages' => 220,
            'language' => 'English'
        ]),
        'images' => json_encode([
            ['url' => 'https://images.pexels.com/photos/46274/pexels-photo-46274.jpeg?auto=compress&cs=tinysrgb&w=600','angle' => 'front','is_primary' => true,'order' => 0],
        ]),
        'moq' => 1,
        'lead_time' => '2-3 days',
        'is_active' => true,
    ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}