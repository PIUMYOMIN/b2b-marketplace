<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a simple key-per-row settings table that tracks which
     * payment methods the admin has enabled or disabled on the platform.
     *
     * Seeded with all methods enabled by default so that existing
     * behaviour is preserved on first deploy.
     */
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('method')->unique();   // e.g. mmqr, kbz_pay, wave_pay, cash_on_delivery
            $table->boolean('enabled')->default(true);
            $table->string('label');              // human-readable name stored for convenience
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed defaults so the checkout page keeps working immediately.
        DB::table('payment_settings')->insert([
            ['method' => 'cash_on_delivery', 'enabled' => true,  'label' => 'Cash on Delivery', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'mmqr',             'enabled' => true,  'label' => 'MMQR Payment',     'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'kbz_pay',          'enabled' => true,  'label' => 'KBZ Pay',           'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'wave_pay',         'enabled' => true,  'label' => 'Wave Pay',          'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'cb_pay',           'enabled' => true,  'label' => 'CB Pay',            'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'aya_pay',          'enabled' => true,  'label' => 'AYA Pay',           'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};