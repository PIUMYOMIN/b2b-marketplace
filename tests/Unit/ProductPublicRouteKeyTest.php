<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPublicRouteKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug_en')->nullable();
            $table->string('slug_mm')->nullable();
            $table->softDeletes();
        });

        DB::table('products')->insert([
            ['id' => 2, 'slug_en' => 'unrelated-product'],
            ['id' => 259, 'slug_en' => '2-lontwe-leather-bag-le'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    public function test_numeric_prefix_slug_is_not_treated_as_a_product_id(): void
    {
        $routeKey = '2-lontwe-leather-bag-le';
        $query = Product::query()->wherePublicRouteKey($routeKey);

        $this->assertSame([$routeKey, $routeKey], $query->getBindings());
        $this->assertSame(259, (int) $query->value('id'));
    }

    public function test_numeric_route_key_still_resolves_legacy_product_ids(): void
    {
        $query = Product::query()->wherePublicRouteKey('2');

        $this->assertSame([2], $query->getBindings());
        $this->assertSame(2, (int) $query->value('id'));
    }
}
