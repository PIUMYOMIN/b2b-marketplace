<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'                 => 'basic',
                'name'                 => 'Basic',
                'description'          => 'For small businesses getting started',
                'price_mmk'            => 0,
                'billing_cycle'        => 'monthly',
                'product_limit'        => 20,
                'commission_rate'      => 0.05,
                'analytics_enabled'    => false,
                'bulk_import_enabled'  => false,
                'priority_support'     => false,
                'custom_storefront'    => false,
                'is_active'            => true,
                'sort_order'           => 1,
            ],
            [
                'slug'                 => 'professional',
                'name'                 => 'Professional',
                'description'          => 'For growing businesses',
                'price_mmk'            => 50000,
                'billing_cycle'        => 'monthly',
                'product_limit'        => 100,
                'commission_rate'      => 0.03,
                'analytics_enabled'    => true,
                'bulk_import_enabled'  => false,
                'priority_support'     => true,
                'custom_storefront'    => false,
                'is_active'            => true,
                'sort_order'           => 2,
            ],
            [
                'slug'                 => 'enterprise',
                'name'                 => 'Enterprise',
                'description'          => 'For large businesses and wholesalers',
                'price_mmk'            => 150000,
                'billing_cycle'        => 'monthly',
                'product_limit'        => -1,       // -1 = unlimited
                'commission_rate'      => 0.01,
                'analytics_enabled'    => true,
                'bulk_import_enabled'  => true,
                'priority_support'     => true,
                'custom_storefront'    => true,
                'is_active'            => true,
                'sort_order'           => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('Subscription plans seeded: Basic, Professional, Enterprise.');
    }
}