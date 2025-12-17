<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
            BusinessTypeSeeder::class
        ]);
    }
}