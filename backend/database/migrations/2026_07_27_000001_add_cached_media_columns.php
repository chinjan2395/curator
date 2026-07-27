<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('cached_thumbnail_path')->nullable()->after('thumbnail_url');
            $table->string('cached_thumbnail_disk', 32)->nullable()->after('cached_thumbnail_path');
            $table->string('cached_thumbnail_mime', 127)->nullable()->after('cached_thumbnail_disk');
        });

        Schema::table('feeds', function (Blueprint $table) {
            $table->string('cached_avatar_path')->nullable()->after('account_avatar_url');
            $table->string('cached_avatar_disk', 32)->nullable()->after('cached_avatar_path');
            $table->string('cached_avatar_mime', 127)->nullable()->after('cached_avatar_disk');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'cached_thumbnail_path',
                'cached_thumbnail_disk',
                'cached_thumbnail_mime',
            ]);
        });

        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn([
                'cached_avatar_path',
                'cached_avatar_disk',
                'cached_avatar_mime',
            ]);
        });
    }
};
