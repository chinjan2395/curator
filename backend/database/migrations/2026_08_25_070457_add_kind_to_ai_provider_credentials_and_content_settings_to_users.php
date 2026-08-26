<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->string('kind', 16)->default('image')->after('user_id');
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            // Add the new unique index first: it also covers `user_id`, so the
            // old index can then be dropped without breaking the FK on user_id.
            $table->unique(['user_id', 'kind', 'provider']);
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'provider']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('ai_content_settings')->nullable()->after('ai_image_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_content_settings');
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            // Same ordering concern as up(): add the old index back before
            // dropping the one currently backing the FK on user_id.
            $table->unique(['user_id', 'provider']);
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'kind', 'provider']);
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
