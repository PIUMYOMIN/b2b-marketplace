<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_mm')->nullable();

            $table->string('slug_en')->unique();
            $table->string('slug_mm')->unique();

            $table->text('description_en')->nullable();
            $table->text('description_mm')->nullable();

            // Document requirements configuration
            $table->boolean('requires_registration')->default(false);
            $table->boolean('requires_tax_document')->default(false);
            $table->boolean('requires_identity_document')->default(true);
            $table->boolean('requires_business_certificate')->default(false);

            // Additional requirements
            $table->json('additional_requirements')->nullable(); // For custom requirements

            // Business rules
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Metadata
            $table->string('icon')->nullable();
            $table->string('color')->nullable()->default('#3B82F6'); // Blue

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug_en');
            $table->index('slug_mm');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_types');
    }
};