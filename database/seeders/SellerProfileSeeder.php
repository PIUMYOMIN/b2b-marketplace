<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SellerProfile;
use App\Models\SellerReview;
use Illuminate\Support\Str;

class SellerProfileSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = User::role('seller')->get();

        foreach ($sellers as $seller) {
            SellerProfile::updateOrCreate(
                ['user_id' => $seller->id],
                [
                    'store_name'     => $seller->name . "'s Store",
                    'store_id'       => 'STORE-' . strtoupper(Str::random(15)),
                    'description'    => "Welcome to {$seller->name}'s store. We offer high-quality products at great prices.",
                    'address'        => fake()->address(),
                    'contact_phone'  => fake()->phoneNumber(),
                    'contact_email'  => $seller->email,
                    'website'        => fake()->url(),
                    'store_slug'     => Str::slug($seller->name . '-store'),
                    'status'         => 'active',
                ]
            );
        }

        $this->command->info("Seeded seller profiles for {$sellers->count()} sellers.");
    }
}