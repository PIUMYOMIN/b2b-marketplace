<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('social_id')->nullable()->after('profile_photo');
            $table->string('social_provider')->nullable()->after('social_id');
            $table->string('identity_document_front')->nullable()->after('social_provider');
            $table->string('identity_document_back')->nullable()->after('identity_document_front');
            $table->string('identity_document_type')->nullable()->after('identity_document_back');
            $table->json('notification_preferences')->nullable()->after('identity_document_type');
            $table->index(['social_provider', 'social_id'], 'users_social_index');
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_social_index');
            $table->dropColumn([
                'social_id',
                'social_provider',
                'identity_document_front',
                'identity_document_back',
                'identity_document_type',
                'notification_preferences',
            ]);
        });
    }
};