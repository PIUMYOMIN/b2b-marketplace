<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('user_id')->unique();
            $table->string('ref_code', 12)->nullable()->unique();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->foreign('referred_by')->references('id')->on('users')->onDelete('set null');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('password');
            $table->enum('type', ['buyer', 'seller', 'admin'])->default('buyer');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('profile_photo')->nullable();
            // Social / OAuth login
            $table->string('social_id')->nullable();
            $table->string('social_provider')->nullable();
            // Identity documents
            $table->string('identity_document_front')->nullable();
            $table->string('identity_document_back')->nullable();
            $table->string('identity_document_type')->nullable();
            // Email notification preferences (JSON toggles)
            $table->json('notification_preferences')->nullable();
            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_code', 6)->nullable();
            $table->timestamp('verification_code_expires_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended', 'disabled', 'restricted'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['social_provider', 'social_id'], 'users_social_index');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};