<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCategoryVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name_en');
            $table->string('name_mm')->nullable();
            $table->string('slug_en');
            $table->string('slug_mm')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_mm')->nullable();
            $table->string('image')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('seller_id');
            $table->boolean('is_active')->default(true);
            $table->string('status');
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->softDeletes();
        });

        Schema::create('seller_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
            $table->string('store_name');
            $table->softDeletes();
        });

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');

        parent::tearDown();
    }

    public function test_inactive_products_do_not_make_categories_visible_in_the_public_tree(): void
    {
        $rootId = $this->createCategory('Root');
        $childId = $this->createCategory('Child', $rootId);
        $sellerId = $this->createPublicSeller();
        $this->createProduct($rootId, $sellerId, false);
        $this->createProduct($childId, $sellerId, false);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson("/api/v1/categories/{$rootId}")
            ->assertNotFound();

        $this->getJson("/api/v1/categories/{$rootId}/descendants")
            ->assertNotFound();
    }

    public function test_public_descendants_only_include_categories_with_visible_products(): void
    {
        $rootId = $this->createCategory('Root');
        $emptyChildId = $this->createCategory('Empty child', $rootId);
        $visibleChildId = $this->createCategory('Visible child', $rootId);
        $sellerId = $this->createPublicSeller();
        $this->createProduct($emptyChildId, $sellerId, false);
        $this->createProduct($visibleChildId, $sellerId, true);

        $this->getJson("/api/v1/categories/{$rootId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.children')
            ->assertJsonPath('data.children.0.id', $visibleChildId);

        $this->getJson("/api/v1/categories/{$rootId}/descendants")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleChildId);
    }

    private function createCategory(string $name, ?int $parentId = null): int
    {
        return DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'name_en' => $name,
            'slug_en' => strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
        ]);
    }

    private function createPublicSeller(): int
    {
        $sellerId = DB::table('users')->insertGetId([]);
        DB::table('seller_profiles')->insert([
            'user_id' => $sellerId,
            'status' => 'approved',
            'store_name' => 'Test Store',
        ]);

        return $sellerId;
    }

    private function createProduct(int $categoryId, int $sellerId, bool $isActive): void
    {
        DB::table('products')->insert([
            'category_id' => $categoryId,
            'seller_id' => $sellerId,
            'is_active' => $isActive,
            'status' => 'approved',
        ]);
    }
}
