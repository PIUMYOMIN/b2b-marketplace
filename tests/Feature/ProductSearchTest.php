<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProductController;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('product_search_test_rows', function ($table): void {
            $table->id();
            $table->string('name_en');
            $table->string('name_mm')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_mm')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('product_search_test_rows');

        parent::tearDown();
    }

    public function test_myanmar_search_matches_a_partial_burmese_product_name(): void
    {
        DB::table('product_search_test_rows')->insert([
            [
                'name_en' => 'Cotton shirt',
                'name_mm' => 'မြန်မာချည်သားအင်္ကျီ',
                'description_en' => null,
                'description_mm' => null,
            ],
            [
                'name_en' => 'Water bottle',
                'name_mm' => 'ပလပ်စတစ်ရေဘူး',
                'description_en' => null,
                'description_mm' => null,
            ],
        ]);

        $query = DB::table('product_search_test_rows');
        (new SearchableProductController)->applySearch($query, 'သားအင်္ကျ');

        $this->assertSame([1], $query->pluck('id')->all());
    }

    public function test_myanmar_search_matches_a_partial_burmese_description(): void
    {
        DB::table('product_search_test_rows')->insert([
            'name_en' => 'Cotton shirt',
            'name_mm' => 'အင်္ကျီ',
            'description_en' => null,
            'description_mm' => 'နူးညံ့သောချည်သားဖြင့်ပြုလုပ်ထားသည်',
        ]);

        $query = DB::table('product_search_test_rows');
        (new SearchableProductController)->applySearch($query, 'ချည်သားဖြင့်');

        $this->assertSame([1], $query->pluck('id')->all());
    }
}

final class SearchableProductController extends ProductController
{
    public function applySearch(Builder $query, string $searchTerm): void
    {
        $this->applyPublicProductSearch($query, $searchTerm);
    }
}
