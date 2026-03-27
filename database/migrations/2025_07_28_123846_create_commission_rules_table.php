<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();

            // Which dimension this rule targets
            $table->enum('type', ['default', 'account_level', 'category', 'business_type']);

            // Polymorphic reference:
            //   account_level  → seller_profiles.user_id
            //   category       → categories.id
            //   business_type  → business_types.id
            //   default        → null (platform-wide fallback)
            $table->unsignedBigInteger('reference_id')->nullable();

            // Human label for this reference (denormalised for readability in admin)
            $table->string('reference_label')->nullable();

            // The rate, e.g. 0.0500 = 5%
            $table->decimal('rate', 5, 4);

            // Optional floor/ceiling for future sliding-scale logic
            $table->decimal('min_rate', 5, 4)->nullable();
            $table->decimal('max_rate', 5, 4)->nullable();

            // Activation window
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // admin user id

            $table->timestamps();

            // Uniqueness: only one active rule per type+reference combo
            $table->unique(['type', 'reference_id'], 'commission_rules_type_ref_unique');
            $table->index(['type', 'is_active']);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};