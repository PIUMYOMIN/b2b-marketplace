<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        Category::query()->delete();

        /*
        |--------------------------------------------------------------------------
        | ROOT CATEGORIES
        |--------------------------------------------------------------------------
        */

        $electronics = $this->root('Electronics', 'electronics', 0.10);
        $mobile = $this->root('Mobile Phones', 'mobile-phones', 0.10);
        $computers = $this->root('Computers', 'computers', 0.10);
        $fashion = $this->root('Fashion & Clothing', 'fashion-clothing', 0.12);
        $home = $this->root('Home & Kitchen', 'home-kitchen', 0.09);
        $beauty = $this->root('Beauty & Personal Care', 'beauty-personal-care', 0.11);
        $food = $this->root('Food & Beverages', 'food-beverages', 0.08);
        $sports = $this->root('Sports & Fitness', 'sports-fitness', 0.10);
        $automotive = $this->root('Automotive Parts & Accessories', 'automotive-parts', 0.08);
        $vehicles = $this->root('Vehicles', 'vehicles', 0.05);
        $industrial = $this->root('Industrial & Construction', 'industrial-construction', 0.07);
        $agriculture = $this->root('Agriculture', 'agriculture', 0.06);
        $office = $this->root('Office Supplies', 'office-supplies', 0.07);
        $books = $this->root('Books', 'books', 0.05);
        $health = $this->root('Health & Medical', 'health-medical', 0.08);
        $pets = $this->root('Pets', 'pets', 0.09);
        $toys = $this->root('Toys & Games', 'toys-games', 0.10);
        $baby = $this->root('Baby & Kids', 'baby-kids', 0.10);
        $travel = $this->root('Travel & Luggage', 'travel-luggage', 0.11);
        $garden = $this->root('Garden & Outdoor', 'garden-outdoor', 0.08);
        $art = $this->root('Art & Craft', 'art-craft', 0.09);
        $music = $this->root('Musical Instruments', 'musical-instruments', 0.12);
        $religious = $this->root('Religious & Spiritual', 'religious', 0.07);
        $party = $this->root('Party & Events', 'party-events', 0.12);
        $digital = $this->root('Digital Products', 'digital-products', 0.20);

        /*
        |--------------------------------------------------------------------------
        | ELECTRONICS
        |--------------------------------------------------------------------------
        */

        $this->child($electronics, 'TV & Monitors', 'tv-monitors');
        $this->child($electronics, 'Audio & Headphones', 'audio-headphones');
        $this->child($electronics, 'Cameras', 'cameras');
        $this->child($electronics, 'Gaming Consoles', 'gaming-consoles');
        $this->child($electronics, 'Drones', 'drones');

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        $this->child($mobile, 'Smartphones', 'smartphones');
        $this->child($mobile, 'Phone Cases', 'phone-cases');
        $this->child($mobile, 'Chargers & Cables', 'chargers');
        $this->child($mobile, 'Power Banks', 'power-banks');
        $this->child($mobile, 'Screen Protectors', 'screen-protectors');
        $this->child($mobile, 'Earphones & Headsets', 'earphones');

        /*
        |--------------------------------------------------------------------------
        | COMPUTERS
        |--------------------------------------------------------------------------
        */

        $this->child($computers, 'Laptops', 'laptops');
        $this->child($computers, 'Desktops', 'desktops');
        $this->child($computers, 'Keyboards & Mouse', 'keyboards');
        $this->child($computers, 'Printers & Scanners', 'printers');
        $this->child($computers, 'Networking', 'networking');
        $this->child($computers, 'Storage Devices', 'storage');

        /*
        |--------------------------------------------------------------------------
        | HOME
        |--------------------------------------------------------------------------
        */

        $this->child($home, 'Furniture', 'furniture');
        $this->child($home, 'Kitchen Appliances', 'kitchen-appliances');
        $this->child($home, 'Home Appliances', 'home-appliances');
        $this->child($home, 'Lighting', 'lighting');
        $this->child($home, 'Storage & Organization', 'storage-organization');
        $this->child($home, 'Bedding & Bath', 'bedding-bath');

        /*
        |--------------------------------------------------------------------------
        | AUTOMOTIVE PARTS
        |--------------------------------------------------------------------------
        */

        $this->child($automotive, 'Vehicle Parts', 'vehicle-parts');
        $this->child($automotive, 'Tires', 'tires');
        $this->child($automotive, 'Lubricants', 'lubricants');
        $this->child($automotive, 'Repair Tools', 'repair-tools');

        /*
        |--------------------------------------------------------------------------
        | VEHICLES
        |--------------------------------------------------------------------------
        */

        $this->child($vehicles, 'Cars', 'cars');
        $this->child($vehicles, 'Motorcycles', 'motorcycles');
        $this->child($vehicles, 'Trucks', 'trucks');

        /*
        |--------------------------------------------------------------------------
        | OFFICE
        |--------------------------------------------------------------------------
        */

        $this->child($office, 'Stationery', 'stationery');
        $this->child($office, 'Office Furniture', 'office-furniture');
        $this->child($office, 'Office Equipment', 'office-equipment');

        /*
        |--------------------------------------------------------------------------
        | BOOKS
        |--------------------------------------------------------------------------
        */

        $this->child($books, 'Educational Books', 'educational-books');
        $this->child($books, 'Magazines', 'magazines');
        $this->child($books, 'Comics', 'comics');

        /*
        |--------------------------------------------------------------------------
        | DIGITAL PRODUCTS (IMPORTANT FOR PYONEA)
        |--------------------------------------------------------------------------
        */

        $this->child($digital, 'Game Topups', 'game-topups');
        $this->child($digital, 'Gift Cards', 'gift-cards');
        $this->child($digital, 'Software', 'software');
        $this->child($digital, 'Ebooks', 'ebooks');

        $this->command->info('Marketplace categories seeded successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function root($name, $slug, $rate)
    {
        $category = Category::create([
            'name_en' => $name,
            'slug_en' => $slug,
            'commission_rate' => $rate,
            'is_active' => true,
        ]);

        $category->makeRoot()->save();

        return $category;
    }

    private function child($parent, $name, $slug)
    {
        $category = Category::create([
            'name_en' => $name,
            'slug_en' => $slug,
            'commission_rate' => null,
            'is_active' => true,
        ]);

        $category->appendToNode($parent)->save();
    }
}