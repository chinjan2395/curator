<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_credentials', function (Blueprint $table) {
            $table->string('profile_image_url')->nullable()->after('account_label');
            $table->unsignedInteger('follower_count')->default(0)->after('profile_image_url');
            $table->json('scopes')->nullable()->after('follower_count');
            $table->string('token_health', 32)->default('unknown')->after('scopes');
            $table->timestamp('last_metadata_synced_at')->nullable()->after('token_health');
            $table->text('access_token_encrypted')->nullable()->after('access_token');
            $table->text('refresh_token_encrypted')->nullable()->after('refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('social_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'profile_image_url',
                'follower_count',
                'scopes',
                'token_health',
                'last_metadata_synced_at',
                'access_token_encrypted',
                'refresh_token_encrypted',
            ]);
        });
    }
};
