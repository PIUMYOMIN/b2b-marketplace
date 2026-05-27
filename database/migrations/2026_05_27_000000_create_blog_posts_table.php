<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title_en');
            $table->string('title_mm')->nullable();
            $table->string('slug')->unique();
            $table->text('excerpt_en')->nullable();
            $table->text('excerpt_mm')->nullable();
            $table->longText('content_en');
            $table->longText('content_mm')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('category')->nullable()->index();
            $table->json('tags')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('seo_title_en')->nullable();
            $table->string('seo_title_mm')->nullable();
            $table->text('seo_description_en')->nullable();
            $table->text('seo_description_mm')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
