<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 4)->default(0.05)->after('amount');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('commission_rate');
            $table->decimal('tax_rate', 5, 4)->default(0.05)->after('tax_amount');
            $table->decimal('platform_revenue', 12, 2)->default(0)->after('tax_rate');
            $table->decimal('seller_payout', 12, 2)->default(0)->after('platform_revenue');
            $table->string('notes')->nullable()->after('seller_payout');
            $table->unsignedBigInteger('commission_rule_id')->nullable()->after('notes');
            $table->foreign('commission_rule_id')
                ->references('id')->on('commission_rules')->onDelete('set null');
        });
    }
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['commission_rule_id']);
            $table->dropColumn([
                'commission_rate',
                'tax_amount',
                'tax_rate',
                'platform_revenue',
                'seller_payout',
                'notes',
                'commission_rule_id',
            ]);
        });
    }
};