<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This seeder is intentionally left empty for now.
        // Orders will be seeded by the ReviewSeeder based on delivered orders.
        // If you want to seed initial orders, you can implement it here.
        
        // Example:
        // Order::factory()->count(50)->create();
        
        // Note: Ensure that the Order model and its relationships are properly defined.
    }
}