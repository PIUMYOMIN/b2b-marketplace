<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Review::class;
    
    public function definition(): array
    {
        return [
            'user_id' => User::whereHas('roles', function ($query) {
                $query->where('name', 'buyer');
            })->inRandomOrder()->first()->id,
            'product_id' => Product::inRandomOrder()->first()->id,
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->paragraph(2),
            'status' => 'approved',
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'updated_at' => now(),
        ];
    }
}