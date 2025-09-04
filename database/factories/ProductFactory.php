<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = \App\Models\Category::pluck('id')->toArray();
        $sellers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'seller');
        })->pluck('id')->toArray();

        return [
            'name' => $this->faker->words(3, true),
            'name_mm' => $this->faker->words(3, true), // In a real app, you'd use Myanmar words
            'description' => $this->faker->paragraph(3),
            'price' => $this->faker->numberBetween(5000, 100000),
            'quantity' => $this->faker->numberBetween(0, 200),
            'category_id' => $this->faker->randomElement($categories),
            'seller_id' => $this->faker->randomElement($sellers),
            'average_rating' => $this->faker->randomFloat(1, 3, 5),
            'review_count' => $this->faker->numberBetween(0, 50),
            'specifications' => json_encode([
                'weight' => $this->faker->randomElement(['500g', '1kg', '2kg', '5kg']),
                'origin' => $this->faker->city,
                'material' => $this->faker->randomElement(['Cotton', 'Bamboo', 'Wood', 'Ceramic']),
            ]),
            'images' => json_encode([
                [
                    'url' => 'https://via.placeholder.com/600x400?text=Product+Image',
                    'angle' => 'front',
                    'is_primary' => true,
                    'order' => 0
                ]
            ]),
            'min_order' => $this->faker->numberBetween(1, 5),
            'lead_time' => $this->faker->randomElement(['1-2 days', '3-5 days', '1 week']),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }
}