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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Store Information
            $table->string('store_name');
            $table->string('store_slug')->unique();
            $table->text('store_description')->nullable();
            $table->string('store_id')->unique();
            $table->string('business_type')->nullable();
            $table->foreignId('business_type_id')->nullable()->constrained('business_types')->onDelete('set null');


            // Business Registration Details
            $table->string('business_registration_number')->nullable();
            $table->string('certificate')->nullable();
            $table->string('tax_id')->nullable();

            // Store Description

            // Contact Information
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('website')->nullable();
            $table->string('account_number')->nullable();

            // Social Media
            $table->string('social_facebook')->nullable();
            $table->string('social_twitter')->nullable();
            $table->string('social_instagram')->nullable();
            $table->string('social_linkedin')->nullable();
            $table->string('social_youtube')->nullable();

            // Address
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('country');
            $table->string('postal_code')->nullable();
            $table->string('location')->nullable();

            // Store Media
            $table->string('store_logo')->nullable();
            $table->string('store_banner')->nullable();

            // Document Fields
            $table->string('business_registration_document')->nullable();
            $table->string('business_certificate')->nullable();
            $table->string('tax_registration_document')->nullable();
            $table->string('identity_document_front')->nullable();
            $table->string('identity_document_back')->nullable();
            $table->longText('additional_documents')->nullable(); // JSON array
            $table->enum('identity_document_type', [
                'national_id',
                'passport',
                'driving_license',
                'other'
            ])->nullable();

            // Status Fields
            $table->enum('status', [
                'setup_pending',
                'pending',
                'approved',
                'active',
                'rejected',
                'suspended',
                'closed'
            ])->default('setup_pending');

            $table->boolean('shipping_enabled')->default(false);

            $table->enum('verification_status', [
                'pending',
                'under_review',
                'verified',
                'rejected'
            ])->default('pending');

            $table->enum('verification_level', [
                'unverified',
                'basic',
                'verified',
                'premium'
            ])->default('unverified');
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            // Document Submission
            $table->boolean('documents_submitted')->default(false);
            $table->timestamp('documents_submitted_at')->nullable();

            // Badge System
            $table->string('badge_type')->nullable();
            $table->timestamp('badge_expires_at')->nullable();

            // Onboarding
            $table->timestamp('onboarding_completed_at')->nullable();

            // Document Review
            $table->enum('document_status', [
                'not_submitted',
                'pending',
                'under_review',
                'approved',
                'rejected'
            ])->default('not_submitted');

            // Onboarding Status
            $table->enum('onboarding_status', [
                'pending',
                'in_progress',
                'completed',
                'rejected'
            ])->default('pending');

            // Progress Tracking
            $table->string('current_step')->nullable();

            $table->text('document_rejection_reason')->nullable();

            // Admin Notes
            $table->text('admin_notes')->nullable();

            // Store policies
            $table->text('return_policy')->nullable();
            $table->text('shipping_policy')->nullable();
            $table->text('warranty_policy')->nullable();
            $table->text('privacy_policy')->nullable();
            $table->text('terms_of_service')->nullable();

            // Payment settings
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->boolean('auto_withdrawal')->default(false);
            $table->decimal('withdrawal_threshold', 15, 2)->default(100000);
            $table->string('preferred_payment_method')->default('bank_transfer');

            // Store status
            $table->boolean('is_active')->default(true);
            $table->boolean('vacation_mode')->default(false);
            $table->text('vacation_message')->nullable();
            $table->date('vacation_start_date')->nullable();
            $table->date('vacation_end_date')->nullable();

            // Display settings
            $table->string('currency')->default('MMK');
            $table->boolean('business_hours_enabled')->default(false);
            $table->json('business_hours')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('store_slug');
            $table->index('status');
            $table->index('verification_status');
            $table->index('business_type_id');
            $table->index(['status', 'verification_status']);
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