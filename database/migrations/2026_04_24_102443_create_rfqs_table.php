<?php
// database/migrations/2026_04_21_create_rfqs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number', 24)->unique();   // RFQ-2026-00042

            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');

            $table->string('product_name');
            $table->string('category')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 20);
            $table->text('specifications')->nullable();
            $table->json('attachments')->nullable();

            $table->decimal('budget_min', 16, 2)->nullable();
            $table->decimal('budget_max', 16, 2)->nullable();
            $table->string('currency', 8)->default('MMK');
            $table->date('deadline');
            $table->text('notes')->nullable();

            $table->boolean('broadcast')->default(true);

            $table->enum('status', [
                'draft',
                'open',
                'quoted',
                'accepted',
                'closed',
                'cancelled',
                'expired',
            ])->default('open');

            $table->unsignedBigInteger('accepted_quote_id')->nullable();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('deadline');
            $table->index(['buyer_id', 'status']);
            $table->index('broadcast');
            $table->index('category');
        });

        Schema::create('rfq_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'seller_id']);
            $table->index('seller_id');
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');

            $table->decimal('unit_price', 16, 2);
            $table->decimal('total_price', 16, 2);
            $table->string('currency', 8)->default('MMK');

            $table->unsignedInteger('delivery_days');
            $table->unsignedInteger('validity_days')->default(7);
            $table->date('valid_until');

            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();

            $table->enum('status', [
                'pending',
                'accepted',
                'rejected',
                'withdrawn',
                'expired',
            ])->default('pending');

            $table->timestamps();

            $table->unique(['rfq_id', 'seller_id']);
            $table->index('status');
            $table->index('valid_until');
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreign('accepted_quote_id')
                ->references('id')->on('rfq_quotes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', fn(Blueprint $t) => $t->dropForeign(['accepted_quote_id']));
        Schema::dropIfExists('rfq_quotes');
        Schema::dropIfExists('rfq_recipients');
        Schema::dropIfExists('rfqs');
    }
};
