<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('store_name');
            $table->string('store_slug');
            $table->string('store_id')->unique();
            $table->enum('business_type', [
                'individual', 'company', 'retail', 'wholesale', 'manufacturer',
                'service', 'partnership', 'private_limited', 'public_limited', 'cooperative'
            ])->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('certificate')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('description')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website')->unique()->nullable();
            $table->string('account_number')->nullable();
            $table->string('social_facebook')->nullable();
            $table->string('social_twitter')->nullable();
            $table->string('social_instagram')->nullable();
            $table->string('social_linkedin')->nullable();
            $table->string('social_youtube')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('store_logo')->nullable();
            $table->string('store_banner')->nullable();
            $table->string('location')->nullable();

            $table->enum('status', [
                'setup_pending',
                'pending',
                'approved', 
                'active', 
                'suspended', 
                'closed'
            ])->default('setup_pending');
            $table->text('admin_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};