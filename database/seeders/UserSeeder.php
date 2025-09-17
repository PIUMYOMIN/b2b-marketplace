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

        // -------------------------
        // Seller Users
        // -------------------------
        $sellers = [
            [
                'name' => 'Tech Distributor',
                'email' => 'tech.seller@b2b.com',
                'phone' => '+959234567890',
                'address' => 'No. 456, Tech Street, Yangon'
            ],
            [
                'name' => 'Electronics World',
                'email' => 'electronics.seller@b2b.com',
                'phone' => '+959345678901',
                'address' => 'No. 789, Electronics Road, Mandalay'
            ],
            [
                'name' => 'Fashion House',
                'email' => 'fashion.seller@b2b.com',
                'phone' => '+959456789012',
                'address' => 'No. 321, Fashion Avenue, Naypyidaw'
            ],
            [
                'name' => 'Home Appliances Co.',
                'email' => 'home.seller@b2b.com',
                'phone' => '+959567890123',
                'address' => 'No. 654, Home Street, Bago'
            ],
            [
                'name' => 'Office Supplies',
                'email' => 'office.seller@b2b.com',
                'phone' => '+959678901234',
                'address' => 'No. 987, Office Road, Mawlamyine'
            ]
        ];

        foreach ($sellers as $index => $seller) {
            $accountNumber = str_pad($index + 2, 6, '0', STR_PAD_LEFT);

            $addressParts = explode(', ', $seller['address']);
            $city = $addressParts[count($addressParts) - 1] ?? null;

            $user = User::create([
                'name'     => $seller['name'],
                'user_id'  => $accountNumber,
                'email'    => $seller['email'],
                'phone'    => $seller['phone'],
                'password' => Hash::make('password'),
                'type'     => 'seller',
                'address'  => $seller['address'] ?? null,
                'city'     => $city,
                'state'    => $city, // Simplified assumption
                'country'  => 'Myanmar',
                'postal_code' => '11000',
            ]);

            $user->assignRole($sellerRole);
        }

        // -------------------------
        // Buyer Users
        // -------------------------
        $buyers = [
            [
                'name' => 'Supermarket Chain',
                'email' => 'supermarket.buyer@b2b.com',
                'phone' => '+959789012345',
                'city' => 'Yangon',
                'address' => 'No. 12, Supermarket Road, Yangon'
            ],
            [
                'name' => 'Electronics Store',
                'email' => 'store.buyer@b2b.com',
                'phone' => '+959890123456',
                'city' => 'Mandalay',
                'address' => 'No. 34, Electronics Street, Mandalay'
            ],
            [
                'name' => 'Fashion Retailer',
                'email' => 'fashion.buyer@b2b.com',
                'phone' => '+959901234567',
                'city' => 'Naypyidaw',
                'address' => 'No. 56, Fashion Avenue, Naypyidaw'
            ],
            [
                'name' => 'Department Store',
                'email' => 'department.buyer@b2b.com',
                'phone' => '+959112345678',
                'city' => 'Bago',
                'address' => 'No. 78, Central Road, Bago'
            ],
            [
                'name' => 'Wholesale Buyer',
                'email' => 'wholesale.buyer@b2b.com',
                'phone' => '+959223456789',
                'city' => 'Mawlamyine',
                'address' => 'No. 90, Wholesale Market Road, Mawlamyine'
            ]
        ];

        foreach ($buyers as $index => $buyer) {
            $accountNumber = str_pad($index + 7, 6, '0', STR_PAD_LEFT);

            $user = User::create([
                'name'     => $buyer['name'],
                'user_id'  => $accountNumber,
                'email'    => $buyer['email'],
                'phone'    => $buyer['phone'],
                'password' => Hash::make('password'),
                'type'     => 'buyer',
                'address'  => $buyer['address'] ?? null,
                'city'     => $buyer['city'] ?? null,
                'state'    => $buyer['city'] ?? null, // Using city as state for simplicity
                'country'  => 'Myanmar',
                'postal_code' => '12000',
            ]);

            $user->assignRole($buyerRole);
        }
    }
}