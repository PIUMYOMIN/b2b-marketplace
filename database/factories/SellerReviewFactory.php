<?php

namespace Database\Factories;

use App\Models\SellerReview;
use App\Models\User;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerReviewFactory extends Factory
{
    protected $model = SellerReview::class;

    public function definition()
    {
        return [
            'seller_id'   => SellerProfile::inRandomOrder()->first()->id ?? 1,
            'user_id'     => User::role('buyer')->inRandomOrder()->first()->id ?? 1,
            'rating'      => $this->faker->numberBetween(1,5),
            'comment'     => $this->faker->sentence(),
            'status'      => 'approved',
            'created_at'  => now(),
            'updated_at'  => now(),
        ];
    }
}