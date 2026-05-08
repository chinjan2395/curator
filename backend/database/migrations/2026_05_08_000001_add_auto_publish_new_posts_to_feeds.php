<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (! Schema::hasColumn('feeds', 'auto_publish_new_posts')) {
                $table->boolean('auto_publish_new_posts')->default(false)->after('last_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (Schema::hasColumn('feeds', 'auto_publish_new_posts')) {
                $table->dropColumn('auto_publish_new_posts');
            }
        });
    }
};
