<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SellerProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create roles if they don't exist
        $adminRole  = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $sellerRole = Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'sanctum']);
        $buyerRole  = Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'sanctum']);

        // -------------------------
        // Admin Users (2 admins)
        // -------------------------
        $admins = [
            [
                'name' => 'Admin User',
                'user_id' => 'ADM001',
                'email' => 'admin@b2b.com',
                'phone' => '+959123456789',
                'password' => Hash::make('password'),
                'type' => 'admin',
                'address' => 'No. 123, Admin Street, Yangon',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11101',
            ],
            [
                'name' => 'System Administrator',
                'user_id' => 'ADM002',
                'email' => 'sysadmin@b2b.com',
                'phone' => '+959987654321',
                'password' => Hash::make('password'),
                'type' => 'admin',
                'address' => '456 Admin Avenue, Bahan Township',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11201',
            ]
        ];

        foreach ($admins as $adminData) {
            $admin = User::create($adminData);
            $admin->assignRole($adminRole);
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('2 Admin users created');
    }
}