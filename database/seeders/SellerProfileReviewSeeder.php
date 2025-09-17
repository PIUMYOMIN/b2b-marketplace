<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\SellerReview;



class SellerProfileReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SellerReview::factory()->count(20)->create();
    }
}