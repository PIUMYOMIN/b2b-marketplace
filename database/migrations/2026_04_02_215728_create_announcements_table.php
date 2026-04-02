<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('type')->default('announcement');
            // types: announcement | promotion | newsletter | advertisement | sponsorship
            $table->string('image')->nullable();           // uploaded image path
            $table->string('cta_label')->nullable();       // e.g. "Shop Now"
            $table->string('cta_url')->nullable();         // link destination
            $table->string('cta_style')->default('primary'); // primary | outline
            $table->string('badge_label')->nullable();     // e.g. "🔥 New", "Limited"
            $table->string('badge_color')->default('green'); // green|red|blue|yellow|purple
            $table->string('target_audience')->default('all'); // all | guests | buyers | sellers
            $table->boolean('is_active')->default(true);
            $table->boolean('show_once')->default(true);   // show only once per day per browser
            $table->integer('delay_seconds')->default(1);  // delay before showing
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};