<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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
        // Admin User
        // -------------------------
        $admin = User::create([
            'name'     => 'Admin User',
            'user_id'  => '000001',
            'email'    => 'admin@b2b.com',
            'phone'    => '+959123456789',
            'password' => Hash::make('password'),
            'type'     => 'admin',
            'address'  => 'No. 123, Admin Street, Yangon',
            'city'     => 'Yangon',
            'state'    => 'Yangon',
            'country'  => 'Myanmar',
            'postal_code' => '11101',
        ]);
        $admin->assignRole($adminRole);
    }
}