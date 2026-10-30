<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'basic')
            ->whereIn('product_limit', [20, 0])
            ->update(['product_limit' => 40]);

        DB::table('subscription_plans')
            ->where('slug', 'professional')
            ->where('product_limit', 140)
            ->update(['product_limit' => 100]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'basic')
            ->where('product_limit', 40)
            ->update(['product_limit' => 20]);
    }
};
