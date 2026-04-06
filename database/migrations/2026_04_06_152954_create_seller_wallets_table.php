<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->onDelete('cascade');

            // Funds held in escrow (buyer has paid, delivery not yet confirmed)
            $table->decimal('escrow_balance', 14, 2)->default(0);

            // Funds available for withdrawal (delivery confirmed, commission deducted)
            $table->decimal('available_balance', 14, 2)->default(0);

            // Lifetime totals for reporting
            $table->decimal('total_earned', 14, 2)->default(0);           // sum of all released seller payouts
            $table->decimal('total_commission_paid', 14, 2)->default(0);  // sum of all commissions deducted
            $table->decimal('total_withdrawn', 14, 2)->default(0);        // sum of all withdrawal transfers

            // COD commission debt (running balance owed to platform)
            $table->decimal('cod_commission_outstanding', 14, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_wallets');
    }
};
