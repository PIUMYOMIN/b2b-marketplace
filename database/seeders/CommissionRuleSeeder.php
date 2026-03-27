<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial commission rules.
 * Run once after migration: php artisan db:seed --class=CommissionRuleSeeder
 *
 * Tier reference_ids are fixed integers (not FK-based):
 *   1 = bronze, 2 = silver, 3 = gold
 * This matches CommissionRateResolver::tierToId().
 */
class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ── Platform default ──────────────────────────────────────────
            [
                'type' => 'default',
                'reference_id' => null,
                'reference_label' => 'Platform Default',
                'rate' => 0.0500,
                'notes' => '5% flat commission — applies when no tier/category/business rule matches',
            ],

            // ── Account level tiers ───────────────────────────────────────
            [
                'type' => 'account_level',
                'reference_id' => 1, // bronze
                'reference_label' => 'Bronze Tier',
                'rate' => 0.0600, // 6% — new sellers
                'notes' => 'New sellers (< 50 completed orders)',
            ],
            [
                'type' => 'account_level',
                'reference_id' => 2, // silver
                'reference_label' => 'Silver Tier',
                'rate' => 0.0500, // 5% — standard
                'notes' => 'Established sellers (50–499 completed orders)',
            ],
            [
                'type' => 'account_level',
                'reference_id' => 3, // gold
                'reference_label' => 'Gold Tier',
                'rate' => 0.0400, // 4% — high volume
                'notes' => 'High-volume sellers (500+ completed orders)',
            ],
        ];

        foreach ($rules as $rule) {
            CommissionRule::updateOrCreate(
                ['type' => $rule['type'], 'reference_id' => $rule['reference_id']],
                array_merge($rule, ['is_active' => true])
            );
        }

        $this->command->info('Commission rules seeded: 1 default + 3 tier rules.');
    }
}