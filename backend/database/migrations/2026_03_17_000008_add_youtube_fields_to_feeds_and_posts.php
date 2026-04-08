<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->string('youtube_channel_id')->nullable()->after('social_credential_id');
            $table->string('youtube_uploads_playlist_id')->nullable()->after('youtube_channel_id');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('title')->nullable()->after('feed_id');
            $table->string('thumbnail_url')->nullable()->after('content');
            $table->string('video_url')->nullable()->after('thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn(['youtube_channel_id', 'youtube_uploads_playlist_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['title', 'thumbnail_url', 'video_url']);
        });
    }
};

