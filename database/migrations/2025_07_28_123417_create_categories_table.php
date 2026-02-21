<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('slug_en')->unique();
            $table->string('name_mm')->nullable()->unique();
            $table->string('slug_mm')->nullable()->unique();
            $table->string('image')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_mm')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10.00);

            // NestedSet columns (parent_id, _lft, _rgt)
            $table->nestedSet();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug_en');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};