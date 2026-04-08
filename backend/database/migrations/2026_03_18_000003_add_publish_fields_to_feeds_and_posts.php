<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (! Schema::hasColumn('feeds', 'public_key')) {
                $table->string('public_key', 64)->nullable()->unique()->after('source_url');
            }
            if (! Schema::hasColumn('feeds', 'publish_settings')) {
                $table->json('publish_settings')->nullable()->after('public_key');
            }
            if (! Schema::hasColumn('feeds', 'last_published_at')) {
                $table->timestamp('last_published_at')->nullable()->after('last_synced_at');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (Schema::hasColumn('feeds', 'last_published_at')) {
                $table->dropColumn('last_published_at');
            }
            if (Schema::hasColumn('feeds', 'publish_settings')) {
                $table->dropColumn('publish_settings');
            }
            if (Schema::hasColumn('feeds', 'public_key')) {
                $table->dropUnique(['public_key']);
                $table->dropColumn('public_key');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'published_at')) {
                $table->dropColumn('published_at');
            }
        });
    }
};

