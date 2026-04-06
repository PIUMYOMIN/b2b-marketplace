<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            /**
             * Delivery fee lifecycle for platform-managed deliveries:
             *
             *  not_applicable  – seller chose their own delivery (platform not involved)
             *  outstanding     – platform quoted a fee; awaiting seller payment
             *  collected       – admin confirmed receipt of payment
             */
            $table->enum('delivery_fee_status', [
                'not_applicable',
                'outstanding',
                'collected',
            ])->default('not_applicable')->after('platform_delivery_fee');

            $table->timestamp('delivery_fee_collected_at')->nullable()->after('delivery_fee_status');

            $table->foreignId('delivery_fee_collected_by')
                ->nullable()
                ->after('delivery_fee_collected_at')
                ->constrained('users')
                ->onDelete('set null');

            $table->string('delivery_fee_collection_ref')->nullable()
                ->after('delivery_fee_collected_by')
                ->comment('Bank transfer ref or receipt number for this payment');

            $table->index(['delivery_fee_status']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_fee_status',
                'delivery_fee_collected_at',
                'delivery_fee_collected_by',
                'delivery_fee_collection_ref',
            ]);
        });
    }
};
