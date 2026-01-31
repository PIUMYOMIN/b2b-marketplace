<?php

namespace Database\Seeders;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SellerProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Get all seller users
        $sellers = User::where('type', 'seller')->get();

        if ($sellers->isEmpty()) {
            $this->command->info('No seller users found. Please run UserSeeder first.');
            return;
        }

        $businessTypes = ['wholesaler', 'manufacturer', 'retailer', 'distributor', 'exporter'];
        $industries = [
            'Electronics',
            'Textiles',
            'Construction',
            'Food & Beverage',
            'Agriculture',
            'Furniture',
            'Handicrafts',
            'Automotive',
            'Healthcare',
            'Beauty & Cosmetics'
        ];

        foreach ($sellers as $index => $seller) {
            // Check if seller already has a profile
            if (!$seller->sellerProfile) {
                $industry = $industries[array_rand($industries)];

                SellerProfile::create([
                    'user_id' => $seller->id,
                    'store_name' => $seller->name,
                    'store_slug' => Str::slug($seller->name . '-' . $industry),
                    'store_description' => $this->getStoreDescription($industry),
                    'contact_phone' => $seller->phone,
                    'contact_email' => $seller->email,
                    'business_type' => $businessTypes[array_rand($businessTypes)],
                    'business_registration_number' => 'REG-' . date('Y') . '-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                    'tax_id' => 'TIN-' . str_pad($index + 1000, 9, '0', STR_PAD_LEFT),
                    'annual_revenue' => rand(100000, 10000000),
                    'number_of_employees' => rand(1, 500),
                    'primary_industry' => $industry,
                    'secondary_industries' => json_encode([$industries[array_rand($industries)], $industries[array_rand($industries)]]),
                    'store_address' => $seller->address,
                    'store_city' => $seller->city,
                    'store_state' => $seller->state,
                    'store_country' => $seller->country,
                    'store_postal_code' => $seller->postal_code,
                    'warehouse_locations' => json_encode([
                        [
                            'address' => $seller->address,
                            'city' => $seller->city,
                            'state' => $seller->state
                        ]
                    ]),
                    'accepts_returns' => true,
                    'return_policy_days' => 30,
                    'shipping_providers' => json_encode(['Myanmar Post', 'Express Delivery Services', 'Private Courier']),
                    'shipping_destinations' => json_encode(['Nationwide', 'International']),
                    'estimated_delivery_time' => '3-7 business days',
                    'payment_methods_accepted' => json_encode(['Bank Transfer', 'Cash on Delivery', 'Mobile Payment', 'Credit/Debit Card']),
                    'minimum_order_amount' => rand(0, 1) ? rand(50000, 200000) : 0,
                    'bulk_discount_available' => true,
                    'bulk_discount_threshold' => rand(10, 100),
                    'bulk_discount_percentage' => rand(5, 20),
                    'customer_support_hours' => 'Monday-Friday: 9AM-6PM, Saturday: 9AM-1PM',
                    'support_phone' => $seller->phone,
                    'support_email' => 'support@' . Str::slug($seller->name) . '.com',
                    'verification_status' => 'pending',
                    'verification_date' => now()->subDays(rand(1, 365)),
                    'is_verified' => false,
                    'store_rating' => rand(35, 50) / 10,
                    'total_ratings' => rand(50, 1000),
                    'response_rate' => rand(85, 100),
                    'response_time' => rand(1, 12) . ' hours',
                    'order_fulfillment_rate' => rand(90, 100),
                    'on_time_delivery_rate' => rand(85, 99),
                    'total_products_listed' => rand(20, 500),
                    'total_orders_completed' => rand(100, 5000),
                    'repeat_customer_rate' => rand(40, 80),
                    'store_categories' => json_encode([
                        ['id' => rand(1, 50), 'name' => $industry],
                        ['id' => rand(51, 100), 'name' => 'Related Products']
                    ]),
                    'certifications' => json_encode(['ISO 9001', 'Myanmar Business License', 'Quality Assurance Certificate']),
                    'awards' => json_encode(['Best Supplier ' . date('Y') - rand(1, 5), 'Quality Excellence Award']),
                    'partnerships' => json_encode(['Major Retail Chains', 'Government Projects', 'International Buyers']),
                    'operating_hours' => json_encode([
                        'monday' => ['open' => '09:00', 'close' => '18:00'],
                        'tuesday' => ['open' => '09:00', 'close' => '18:00'],
                        'wednesday' => ['open' => '09:00', 'close' => '18:00'],
                        'thursday' => ['open' => '09:00', 'close' => '18:00'],
                        'friday' => ['open' => '09:00', 'close' => '18:00'],
                        'saturday' => ['open' => '09:00', 'close' => '13:00'],
                        'sunday' => ['open' => null, 'close' => null]
                    ]),
                    'holiday_schedule' => json_encode([
                        'Thingyan Water Festival' => 'April 13-16',
                        'New Year' => 'January 1',
                        'Christmas' => 'December 25'
                    ]),
                    'is_featured' => $index < 3, // First 3 sellers are featured
                    'featured_until' => $index < 3 ? now()->addDays(30) : null,
                    'is_active' => true,
                    'subscription_plan' => ['basic', 'pro', 'enterprise'][$index % 3],
                    'subscription_expires_at' => now()->addDays(rand(30, 365)),
                    'commission_rate' => (rand(5, 15) / 100),
                    'store_visits' => rand(1000, 50000),
                    'monthly_sales' => rand(5000000, 50000000),
                    'yearly_growth' => rand(10, 100) . '%',
                    'export_capability' => rand(0, 1),
                    'export_countries' => json_encode(['Thailand', 'Singapore', 'China', 'Japan']),
                    'import_capability' => rand(0, 1),
                    'languages_supported' => json_encode(['Burmese', 'English', 'Chinese']),
                    'created_at' => now()->subDays(rand(30, 730)),
                    'updated_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('Seller profiles created/updated successfully!');
    }

    /**
     * Generate store description based on industry
     */
    private function getStoreDescription($industry)
    {
        $descriptions = [
            'Electronics' => 'Leading supplier of electronic components, gadgets, and appliances with warranty support.',
            'Textiles' => 'Premium textile manufacturer providing quality fabrics for fashion and industrial use.',
            'Construction' => 'Reliable construction materials supplier with nationwide delivery network.',
            'Food & Beverage' => 'Wholesale food products supplier ensuring quality and freshness.',
            'Agriculture' => 'Agricultural equipment and supplies for modern farming needs.',
            'Furniture' => 'Custom furniture manufacturer using sustainable materials and traditional craftsmanship.',
            'Handicrafts' => 'Authentic Myanmar handicrafts preserving cultural heritage.',
            'Automotive' => 'Automotive parts and accessories for all vehicle types.',
            'Healthcare' => 'Medical supplies and healthcare equipment meeting international standards.',
            'Beauty & Cosmetics' => 'Beauty products and cosmetics from trusted brands.'
        ];

        return $descriptions[$industry] ?? 'Quality products and reliable service since ' . (date('Y') - rand(5, 30));
    }
}
