<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'is_active'], 'products_category_active_idx');
            $table->index(['seller_id', 'is_active'], 'products_seller_active_idx');
            $table->index(['is_featured', 'is_active', 'created_at'], 'products_featured_active_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_active_idx');
            $table->dropIndex('products_seller_active_idx');
            $table->dropIndex('products_featured_active_created_idx');
        });
    }
};
