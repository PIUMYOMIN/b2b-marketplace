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

        // -------------------------
        // Seller Users (10 sellers)
        // -------------------------
        $sellers = [
            [
                'name' => 'Golden Myanmar Trading',
                'user_id' => 'SEL001',
                'email' => 'goldenmyanmar@b2b.com',
                'phone' => '+95991234567',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'No. 45, Merchant Road, Botahtaung',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11161',
            ],
            [
                'name' => 'Mandalay Hardware Co.',
                'user_id' => 'SEL002',
                'email' => 'mandalayhardware@b2b.com',
                'phone' => '+95982345678',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => '78th Street, Between 26th & 27th, Mandalay',
                'city' => 'Mandalay',
                'state' => 'Mandalay',
                'country' => 'Myanmar',
                'postal_code' => '05011',
            ],
            [
                'name' => 'Yangon Textile Ltd',
                'user_id' => 'SEL003',
                'email' => 'yangontextile@b2b.com',
                'phone' => '+95973456789',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Industrial Zone 3, Hlaing Tharyar',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11401',
            ],
            [
                'name' => 'Shwe Plastic Manufacturing',
                'user_id' => 'SEL004',
                'email' => 'shweplastic@b2b.com',
                'phone' => '+95964567890',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'No. 12, Pyay Road, Sanchaung',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11111',
            ],
            [
                'name' => 'Naypyidaw Construction Supply',
                'user_id' => 'SEL005',
                'email' => 'naypyidawconstruction@b2b.com',
                'phone' => '+95955678901',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Zabuthiri Township, Naypyidaw',
                'city' => 'Naypyidaw',
                'state' => 'Naypyidaw',
                'country' => 'Myanmar',
                'postal_code' => '15011',
            ],
            [
                'name' => 'Ayarwaddy Food Products',
                'user_id' => 'SEL006',
                'email' => 'ayarwaddyfood@b2b.com',
                'phone' => '+95946789012',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Pathein Industrial Zone, Ayeyarwady',
                'city' => 'Pathein',
                'state' => 'Ayeyarwady',
                'country' => 'Myanmar',
                'postal_code' => '10012',
            ],
            [
                'name' => 'Bagan Handicrafts',
                'user_id' => 'SEL007',
                'email' => 'baganhandicrafts@b2b.com',
                'phone' => '+95937890123',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Nyaung-U Road, Bagan',
                'city' => 'Bagan',
                'state' => 'Mandalay',
                'country' => 'Myanmar',
                'postal_code' => '05213',
            ],
            [
                'name' => 'Mawlamyine Electronics',
                'user_id' => 'SEL008',
                'email' => 'mawlamyineelectronics@b2b.com',
                'phone' => '+95928901234',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Main Road, Mawlamyine',
                'city' => 'Mawlamyine',
                'state' => 'Mon',
                'country' => 'Myanmar',
                'postal_code' => '12011',
            ],
            [
                'name' => 'Taunggyi Agricultural Supply',
                'user_id' => 'SEL009',
                'email' => 'taunggyiagricultural@b2b.com',
                'phone' => '+95919012345',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Kalaw Road, Taunggyi',
                'city' => 'Taunggyi',
                'state' => 'Shan',
                'country' => 'Myanmar',
                'postal_code' => '06011',
            ],
            [
                'name' => 'Hpa-an Furniture Workshop',
                'user_id' => 'SEL010',
                'email' => 'hpaanfurniture@b2b.com',
                'phone' => '+95910123456',
                'password' => Hash::make('password'),
                'type' => 'seller',
                'address' => 'Industrial Area, Hpa-an',
                'city' => 'Hpa-an',
                'state' => 'Kayin',
                'country' => 'Myanmar',
                'postal_code' => '13011',
            ]
        ];

        $sellerUsers = [];
        foreach ($sellers as $sellerData) {
            $seller = User::create($sellerData);
            $seller->assignRole($sellerRole);
            $sellerUsers[] = $seller;
        }

        // -------------------------
        // Buyer Users (15 buyers)
        // -------------------------
        $buyers = [
            [
                'name' => 'Aung Ko Ko',
                'user_id' => 'BUY001',
                'email' => 'aungkoko@buyer.com',
                'phone' => '+95923456789',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'No. 34, 45th Street, Botahtaung',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11161',
            ],
            [
                'name' => 'Mya Mya',
                'user_id' => 'BUY002',
                'email' => 'myamya@buyer.com',
                'phone' => '+95934567890',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => '78th Street, Mandalay',
                'city' => 'Mandalay',
                'state' => 'Mandalay',
                'country' => 'Myanmar',
                'postal_code' => '05011',
            ],
            [
                'name' => 'Ko Zaw',
                'user_id' => 'BUY003',
                'email' => 'kozaw@buyer.com',
                'phone' => '+95945678901',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Hlaing Township, Yangon',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11051',
            ],
            [
                'name' => 'Daw Hla',
                'user_id' => 'BUY004',
                'email' => 'dawhla@buyer.com',
                'phone' => '+95956789012',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Sanchaung Township',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11111',
            ],
            [
                'name' => 'Tin Oo',
                'user_id' => 'BUY005',
                'email' => 'tin.oo@buyer.com',
                'phone' => '+95967890123',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Zabuthiri Township',
                'city' => 'Naypyidaw',
                'state' => 'Naypyidaw',
                'country' => 'Myanmar',
                'postal_code' => '15011',
            ],
            [
                'name' => 'Su Su',
                'user_id' => 'BUY006',
                'email' => 'susu@buyer.com',
                'phone' => '+95978901234',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Pathein, Ayeyarwady Region',
                'city' => 'Pathein',
                'state' => 'Ayeyarwady',
                'country' => 'Myanmar',
                'postal_code' => '10012',
            ],
            [
                'name' => 'Min Min',
                'user_id' => 'BUY007',
                'email' => 'minmin@buyer.com',
                'phone' => '+95989012345',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Nyaung-U, Bagan',
                'city' => 'Bagan',
                'state' => 'Mandalay',
                'country' => 'Myanmar',
                'postal_code' => '05213',
            ],
            [
                'name' => 'Khin Khin',
                'user_id' => 'BUY008',
                'email' => 'khinkhin@buyer.com',
                'phone' => '+95990123456',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Mawlamyine, Mon State',
                'city' => 'Mawlamyine',
                'state' => 'Mon',
                'country' => 'Myanmar',
                'postal_code' => '12011',
            ],
            [
                'name' => 'Zaw Zaw',
                'user_id' => 'BUY009',
                'email' => 'zawzaw@buyer.com',
                'phone' => '+95901234567',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Taunggyi, Shan State',
                'city' => 'Taunggyi',
                'state' => 'Shan',
                'country' => 'Myanmar',
                'postal_code' => '06011',
            ],
            [
                'name' => 'Nilar',
                'user_id' => 'BUY010',
                'email' => 'nilar@buyer.com',
                'phone' => '+95912345098',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Hpa-an, Kayin State',
                'city' => 'Hpa-an',
                'state' => 'Kayin',
                'country' => 'Myanmar',
                'postal_code' => '13011',
            ],
            [
                'name' => 'Business Solutions Ltd.',
                'user_id' => 'BUY011',
                'email' => 'business@buyer.com',
                'phone' => '+95923450987',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Union Business Center, Yangon',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11181',
            ],
            [
                'name' => 'Retail Mart Chain',
                'user_id' => 'BUY012',
                'email' => 'retailmart@buyer.com',
                'phone' => '+95934509876',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Multiple Locations, Yangon',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11191',
            ],
            [
                'name' => 'Construction Company',
                'user_id' => 'BUY013',
                'email' => 'construction@buyer.com',
                'phone' => '+95945698765',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Industrial Zone, Mandalay',
                'city' => 'Mandalay',
                'state' => 'Mandalay',
                'country' => 'Myanmar',
                'postal_code' => '05021',
            ],
            [
                'name' => 'Hotel Supply Co.',
                'user_id' => 'BUY014',
                'email' => 'hotelsupply@buyer.com',
                'phone' => '+95956787654',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Hotel Zone, Naypyidaw',
                'city' => 'Naypyidaw',
                'state' => 'Naypyidaw',
                'country' => 'Myanmar',
                'postal_code' => '15021',
            ],
            [
                'name' => 'School Supplies',
                'user_id' => 'BUY015',
                'email' => 'schoolsupplies@buyer.com',
                'phone' => '+95967876543',
                'password' => Hash::make('password'),
                'type' => 'buyer',
                'address' => 'Education Road, Yangon',
                'city' => 'Yangon',
                'state' => 'Yangon',
                'country' => 'Myanmar',
                'postal_code' => '11141',
            ]
        ];

        foreach ($buyers as $buyerData) {
            $buyer = User::create($buyerData);
            $buyer->assignRole($buyerRole);
        }

        // Create seller profiles for sellers - MATCHING YOUR ACTUAL MIGRATION
        $this->createSellerProfiles($sellerUsers);

        $this->command->info('Users seeded successfully!');
        $this->command->info('2 Admin users created');
        $this->command->info('10 Seller users created with profiles');
        $this->command->info('15 Buyer users created');
    }

    /**
     * Create seller profiles for seller users - UPDATED TO MATCH MIGRATION
     */
    private function createSellerProfiles(array $sellers)
    {
        $businessTypes = ['wholesaler', 'manufacturer', 'retailer', 'distributor'];
        $statuses = ['setup_pending', 'pending', 'approved', 'active'];
        $verificationStatuses = ['pending', 'under_review', 'verified', 'rejected'];
        $identityDocumentTypes = ['national_id', 'passport', 'driving_license', 'other'];

        $storeNames = [
            'Golden Myanmar Trading Center',
            'Mandalay Hardware & Tools',
            'Yangon Textile Factory Outlet',
            'Shwe Plastic Products',
            'Naypyidaw Construction Materials',
            'Ayarwaddy Food Wholesale',
            'Bagan Traditional Handicrafts',
            'Mawlamyine Electronics Store',
            'Taunggyi Farm Supplies',
            'Hpa-an Quality Furniture'
        ];

        $descriptions = [
            'Leading supplier of quality products since 2010',
            'Your trusted partner for construction materials',
            'Premium textile manufacturer with 15 years experience',
            'High-quality plastic products at wholesale prices',
            'Complete construction solutions for your projects',
            'Fresh food products directly from farms',
            'Authentic Myanmar handicrafts and souvenirs',
            'Latest electronics and appliances at best prices',
            'Agricultural equipment and supplies experts',
            'Custom furniture made from quality materials'
        ];

        foreach ($sellers as $index => $seller) {
            $storeName = $storeNames[$index] ?? $seller->name;
            $storeSlug = Str::slug($storeName);
            $storeId = 'STORE-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);

            SellerProfile::create([
                'user_id' => $seller->id,
                'store_name' => $storeName,
                'store_slug' => $storeSlug,
                'store_id' => $storeId,
                'business_type' => $businessTypes[array_rand($businessTypes)],
                'store_description' => $descriptions[$index] ?? 'Quality products at competitive prices',

                // Contact Information
                'contact_email' => $seller->email,
                'contact_phone' => $seller->phone,
                'website' => 'https://www.' . $storeSlug . '.com',
                'account_number' => 'ACC-' . str_pad($index + 1000, 8, '0', STR_PAD_LEFT),

                // Social Media
                'social_facebook' => 'https://facebook.com/' . $storeSlug,
                'social_instagram' => 'https://instagram.com/' . $storeSlug,
                'social_twitter' => 'https://twitter.com/' . $storeSlug,
                'social_linkedin' => 'https://linkedin.com/company/' . $storeSlug,
                'social_youtube' => null,

                // Address
                'address' => $seller->address,
                'city' => $seller->city,
                'state' => $seller->state,
                'country' => $seller->country,
                'postal_code' => $seller->postal_code,
                'location' => $seller->city . ', ' . $seller->state,

                // Business Registration
                'business_registration_number' => 'BRN-' . str_pad($index + 1000, 8, '0', STR_PAD_LEFT),
                'tax_id' => 'TIN-' . str_pad($index + 1000, 9, '0', STR_PAD_LEFT),

                // Status Fields
                'status' => $statuses[array_rand(['approved', 'active'])], // Mostly approved/active
                'verification_status' => 'verified',
                'verification_level' => ['basic', 'verified', 'premium'][array_rand([1, 2])], // Mostly verified or premium
                // 'verification_date' => now()->subDays(rand(30, 365)),
                'verification_notes' => 'Documents verified successfully',
                'is_verified' => true,

                // Document Submission
                'documents_submitted' => true,
                'documents_submitted_at' => now()->subDays(rand(31, 365)),

                // Document Status
                'document_status' => 'approved',
                'document_rejection_reason' => null,

                // Onboarding
                'onboarding_status' => 'completed',
                'onboarding_completed_at' => now()->subDays(rand(31, 365)),
                'current_step' => null,

                // Document Types
                'identity_document_type' => $identityDocumentTypes[array_rand($identityDocumentTypes)],
                'business_registration_document' => '/documents/business_registration_' . ($index + 1) . '.pdf',
                'tax_registration_document' => '/documents/tax_registration_' . ($index + 1) . '.pdf',
                'identity_document_front' => '/documents/id_front_' . ($index + 1) . '.jpg',
                'identity_document_back' => '/documents/id_back_' . ($index + 1) . '.jpg',
                'additional_documents' => json_encode([
                    ['name' => 'Bank Statement', 'file' => '/documents/bank_statement_' . ($index + 1) . '.pdf'],
                    ['name' => 'Utility Bill', 'file' => '/documents/utility_bill_' . ($index + 1) . '.pdf'],
                ]),

                // Badge System (only for some sellers)
                'badge_type' => rand(0, 3) == 0 ? 'premium_seller' : null, // 25% chance
                'badge_expires_at' => rand(0, 3) == 0 ? now()->addDays(rand(30, 365)) : null,

                // Admin Notes
                'admin_notes' => rand(0, 1) ? 'Good standing seller with positive reviews' : null,

                // Store Media
                'store_logo' => '/store-logos/store-' . ($index + 1) . '.png',
                'store_banner' => '/store-banners/banner-' . ($index + 1) . '.jpg',

                'created_at' => now()->subDays(rand(60, 730)),
                'updated_at' => now()->subDays(rand(1, 60)),
            ]);
        }
    }
}
