<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get seller and category IDs
        $sellerId = User::whereHas('roles', function ($query) {
            $query->where('name', 'seller');
        })->first()->id;

        $categoryId = Category::first()->id;

        $products = [
            [
                'name' => 'Organic Rice',
                'description' => 'High-quality organic rice grown in Myanmar with sustainable farming practices. Perfect for daily consumption and special occasions.',
                'price' => 45000,
                'quantity' => 100,
                'category_id' => $categoryId,
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
                        'url' => 'https://images.unsplash.com/photo-1547496502-affa22d38842?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
                        'angle' => 'front',
                        'is_primary' => true,
                        'order' => 0
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
                        'angle' => 'package',
                        'is_primary' => false,
                        'order' => 1
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
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
                'category_id' => $categoryId,
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
                        'url' => 'https://images.unsplash.com/photo-1604176354204-9266227a5e9b?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
                        'angle' => 'front',
                        'is_primary' => true,
                        'order' => 0
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1586023492125-27a5ce4c2d27?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
                        'angle' => 'side',
                        'is_primary' => false,
                        'order' => 1
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1595344152551-2a703f84d729?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
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
                'category_id' => $categoryId,
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
                        'url' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
                        'angle' => 'front',
                        'is_primary' => true,
                        'order' => 0
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
                        'angle' => 'back',
                        'is_primary' => false,
                        'order' => 1
                    ],
                    [
                        'url' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80',
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
            ]
        ];

        // Insert products
        foreach ($products as $product) {
            Product::create($product);
        }

        // Add more random products for pagination testing
        Product::factory()->count(20)->create([
            'category_id' => $categoryId,
            'seller_id' => $sellerId,
        ]);
    }
}