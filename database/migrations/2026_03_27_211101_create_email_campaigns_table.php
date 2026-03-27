<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->enum('audience', [
                'newsletter_subscribers', 'all_buyers', 'all_sellers',
                'buyers_by_city', 'sellers_by_tier', 'custom_ids',
            ])->default('newsletter_subscribers');
            $table->json('audience_filter')->nullable();
            $table->enum('status', ['draft','scheduled','sending','sent','cancelled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('bounced_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('email_campaigns'); }
};