<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_credentials', function (Blueprint $table) {
            $table->string('account_id')->nullable()->after('provider');
            $table->string('account_label')->nullable()->after('account_id');
            $table->unique(['user_id', 'provider', 'account_id'], 'social_credentials_user_provider_account_unique');
        });
    }

    public function down(): void
    {
        Schema::table('social_credentials', function (Blueprint $table) {
            $table->dropUnique('social_credentials_user_provider_account_unique');
            $table->dropColumn(['account_id', 'account_label']);
        });
    }
};
