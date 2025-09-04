<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create roles if they don't exist
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $sellerRole = Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'sanctum']);
        $buyerRole = Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'sanctum']);


        // Admin User (account_number: 000001)
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@b2b.com',
            'phone' => '+959123456789',
            'password' => Hash::make('password'),
            'type' => 'admin',
            'company_name' => 'B2B Marketplace Admin',
            'address' => 'No. 123, Admin Street, Yangon',
            'city' => 'Yangon',
            'state' => 'Yangon',
            'account_number' => '000001'
        ]);
        $admin->assignRole($adminRole);

        // Seller Users (account_number: 000002 to 000006)
        $sellers = [
            [
                'name' => 'Tech Distributor',
                'email' => 'tech.seller@b2b.com',
                'phone' => '+959234567890',
                'company_name' => 'Yangon Tech Distributors',
                'address' => 'No. 456, Tech Street, Yangon'
            ],
            [
                'name' => 'Electronics World',
                'email' => 'electronics.seller@b2b.com',
                'phone' => '+959345678901',
                'company_name' => 'Mandalay Electronics World',
                'address' => 'No. 789, Electronics Road, Mandalay'
            ],
            [
                'name' => 'Fashion House',
                'email' => 'fashion.seller@b2b.com',
                'phone' => '+959456789012',
                'company_name' => 'Naypyidaw Fashion House',
                'address' => 'No. 321, Fashion Avenue, Naypyidaw'
            ],
            [
                'name' => 'Home Appliances Co.',
                'email' => 'home.seller@b2b.com',
                'phone' => '+959567890123',
                'company_name' => 'Bago Home Appliances',
                'address' => 'No. 654, Home Street, Bago'
            ],
            [
                'name' => 'Office Supplies',
                'email' => 'office.seller@b2b.com',
                'phone' => '+959678901234',
                'company_name' => 'Mawlamyine Office Supplies',
                'address' => 'No. 987, Office Road, Mawlamyine'
            ]
        ];

        foreach ($sellers as $index => $seller) {
            $accountNumber = str_pad($index + 2, 6, '0', STR_PAD_LEFT);
            $addressParts = explode(', ', $seller['address']);
            $city = $addressParts[count($addressParts) - 1];
            
            $user = User::create([
                'name' => $seller['name'],
                'email' => $seller['email'],
                'phone' => $seller['phone'],
                'password' => Hash::make('password'),
                'type' => 'seller',
                'company_name' => $seller['company_name'],
                'address' => $seller['address'],
                'city' => $city,
                'state' => $city, // Using city as state for simplicity
                'account_number' => $accountNumber
            ]);
            $user->assignRole($sellerRole);
        }

        // Buyer Users (account_number: 000007 to 000011)
        $buyers = [
            [
                'name' => 'Supermarket Chain',
                'email' => 'supermarket.buyer@b2b.com',
                'phone' => '+959789012345',
                'company_name' => 'Yangon Supermarket Chain',
                'city' => 'Yangon'
            ],
            [
                'name' => 'Electronics Store',
                'email' => 'store.buyer@b2b.com',
                'phone' => '+959890123456',
                'company_name' => 'Mandalay Electronics Store',
                'city' => 'Mandalay'
            ],
            [
                'name' => 'Fashion Retailer',
                'email' => 'fashion.buyer@b2b.com',
                'phone' => '+959901234567',
                'company_name' => 'Naypyidaw Fashion Retail',
                'city' => 'Naypyidaw'
            ],
            [
                'name' => 'Department Store',
                'email' => 'department.buyer@b2b.com',
                'phone' => '+959112345678',
                'company_name' => 'Bago Department Store',
                'city' => 'Bago'
            ],
            [
                'name' => 'Wholesale Buyer',
                'email' => 'wholesale.buyer@b2b.com',
                'phone' => '+959223456789',
                'company_name' => 'Mawlamyine Wholesale',
                'city' => 'Mawlamyine'
            ]
        ];

        foreach ($buyers as $index => $buyer) {
            $accountNumber = str_pad($index + 7, 6, '0', STR_PAD_LEFT);
            $user = User::create([
                'name' => $buyer['name'],
                'email' => $buyer['email'],
                'phone' => $buyer['phone'],
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'company_name' => $buyer['company_name'],
                'address' => $buyer['company_name'] . ', ' . $buyer['city'],
                'city' => $buyer['city'],
                'state' => $buyer['city'], // Using city as state for simplicity
                'account_number' => $accountNumber
            ]);
            $user->assignRole($buyerRole);
        }
    }
}