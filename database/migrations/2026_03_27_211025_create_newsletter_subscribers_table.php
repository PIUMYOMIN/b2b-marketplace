<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('confirm_token', 64)->nullable()->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->boolean('pref_promotions')->default(true);
            $table->boolean('pref_new_sellers')->default(true);
            $table->boolean('pref_product_updates')->default(true);
            $table->boolean('pref_platform_news')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('source')->default('website');
            $table->timestamps();
            $table->index(['confirmed_at', 'unsubscribed_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};