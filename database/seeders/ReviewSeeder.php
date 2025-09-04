<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Review;
use App\Models\User;
use App\Models\Product;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing reviews
        DB::table('reviews')->truncate();

        // Get all buyer users and products
        $buyers = User::whereHas('roles', function ($query) {
            $query->where('name', 'buyer');
        })->get();

        $products = Product::where('is_active', true)->get();

        if ($buyers->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No buyers or products found. Please run UserSeeder and ProductSeeder first.');
            return;
        }

        $reviews = [];

        // Create reviews for each product
        foreach ($products as $product) {
            // Get random number of reviews for this product (between 3-8)
            $numberOfReviews = rand(3, 8);
            
            // Select random buyers for this product's reviews
            $productBuyers = $buyers->random(min($numberOfReviews, $buyers->count()));
            
            foreach ($productBuyers as $buyer) {
                $reviews[] = [
                    'user_id' => $buyer->id,
                    'product_id' => $product->id,
                    'rating' => $this->generateRating($product),
                    'comment' => $this->generateComment($product, $buyer),
                    'status' => 'approved', // All seeded reviews are approved
                    'created_at' => now()->subDays(rand(1, 90)),
                    'updated_at' => now(),
                ];
            }

            // Insert reviews in batches
            if (count($reviews) >= 100) {
                Review::insert($reviews);
                $reviews = [];
            }
        }

        // Insert any remaining reviews
        if (!empty($reviews)) {
            Review::insert($reviews);
        }

        // Update product ratings after creating reviews
        $this->updateProductRatings();

        $this->command->info('Successfully seeded ' . Review::count() . ' reviews.');
    }

    /**
     * Generate realistic rating based on product type
     */
    private function generateRating(Product $product): int
    {
        // Higher probability of good ratings with some variation
        $ratingDistribution = [
            1 => 5,  // 5% chance of 1 star
            2 => 10, // 10% chance of 2 stars
            3 => 20, // 20% chance of 3 stars
            4 => 30, // 30% chance of 4 stars
            5 => 35, // 35% chance of 5 stars
        ];

        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($ratingDistribution as $rating => $percentage) {
            $cumulative += $percentage;
            if ($random <= $cumulative) {
                return $rating;
            }
        }

        return 4; // Default to 4 stars
    }

    /**
     * Generate realistic comment based on product and rating
     */
    private function generateComment(Product $product, User $user): string
    {
        $productName = $product->name;
        $userName = $user->name;

        $positiveComments = [
            "Excellent {$productName}! The quality exceeded my expectations. Highly recommended!",
            "Very satisfied with my purchase. The {$productName} is durable and well-made.",
            "Great product at a reasonable price. Shipping was fast and packaging was secure.",
            "I've been using this {$productName} for a while now and it's been perfect for my needs.",
            "Outstanding quality! The attention to detail is impressive. Will buy again soon.",
            "This {$productName} has made my life so much easier. Worth every penny!",
            "Beautiful craftsmanship. The {$productName} looks even better in person than in photos.",
            "Fast delivery and excellent customer service. The product works perfectly.",
            "I'm thoroughly impressed with this {$productName}. It's exactly what I needed.",
            "Top-notch quality! The materials used are premium and it shows in the final product."
        ];

        $neutralComments = [
            "The {$productName} is decent for the price. Does what it's supposed to do.",
            "Good basic product. Nothing extraordinary but gets the job done.",
            "Average quality. Expected a bit more based on the description.",
            "It's okay. The {$productName} works but could be improved in some areas.",
            "Satisfactory product. Delivery took a bit longer than expected.",
            "The {$productName} is functional but the design could be better.",
            "Not bad, but not great either. It serves its purpose adequately.",
            "Decent product overall. The packaging could have been better though.",
            "Average experience. The product works but has some minor flaws.",
            "It's alright. Does what it needs to do without any special features."
        ];

        $negativeComments = [
            "Disappointed with the {$productName}. The quality doesn't match the price.",
            "Not what I expected. The product arrived damaged and had to be returned.",
            "Poor quality materials. The {$productName} broke after just a few uses.",
            "Very disappointed. The product description was misleading.",
            "The {$productName} doesn't work as advertised. Would not recommend.",
            "Low quality product. Expected much better based on the photos.",
            "Arrived late and damaged. Customer service was unhelpful.",
            "Not worth the money. The {$productName} is poorly made and flimsy.",
            "Extremely disappointed. The product failed to meet basic expectations.",
            "Would not buy again. The {$productName} has multiple design flaws."
        ];

        // Get a random comment based on rating (we'll determine rating later)
        $comments = array_merge($positiveComments, $neutralComments, $negativeComments);
        
        return $comments[array_rand($comments)];
    }

    /**
     * Update all product ratings after seeding
     */
    private function updateProductRatings(): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            $approvedReviews = Review::where('product_id', $product->id)
                ->where('status', 'approved')
                ->get();

            if ($approvedReviews->count() > 0) {
                $averageRating = $approvedReviews->avg('rating');
                $reviewCount = $approvedReviews->count();

                $product->update([
                    'average_rating' => round($averageRating, 2),
                    'review_count' => $reviewCount
                ]);
            }
        }

        $this->command->info('Updated product ratings for ' . $products->count() . ' products.');
    }
}