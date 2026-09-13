<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE conversations MODIFY context_type ENUM('product', 'rfq', 'order', 'general') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::table('conversations')
            ->where('context_type', 'general')
            ->update(['context_type' => 'product']);

        DB::statement("ALTER TABLE conversations MODIFY context_type ENUM('product', 'rfq', 'order') NOT NULL");
    }
};
