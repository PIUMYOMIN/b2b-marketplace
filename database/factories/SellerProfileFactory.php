<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SellerProfile;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SellerProfile>
 */
class SellerProfileFactory extends Factory
{
    protected $model = SellerProfile::class;

    public function definition(): array
    {
        return [
            'store_name'     => $this->faker->company,
            'store_logo'     => $this->faker->logo,
            'description'    => $this->faker->paragraph,
            'address'        => $this->faker->address,
            'contact_phone'  => $this->faker->phoneNumber,
            'contact_email'  => $this->faker->unique()->safeEmail,
            'website'        => $this->faker->url,
            'store_slug'     => $this->faker->unique()->slug,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ];
    }
}