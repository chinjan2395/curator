<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('content_type', 50)->nullable()->after('external_id');
            $table->string('post_url')->nullable()->after('video_url');
            $table->unsignedInteger('likes')->default(0)->after('published_at');
            $table->unsignedInteger('comments')->default(0)->after('likes');
            $table->unsignedInteger('shares')->default(0)->after('comments');
            $table->unsignedInteger('views')->default(0)->after('shares');
            $table->unsignedInteger('saves')->default(0)->after('views');
            $table->unsignedInteger('reach')->default(0)->after('saves');
            $table->json('hashtags')->nullable()->after('reach');
            $table->json('raw_data')->nullable()->after('hashtags');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'content_type',
                'post_url',
                'likes',
                'comments',
                'shares',
                'views',
                'saves',
                'reach',
                'hashtags',
                'raw_data',
            ]);
        });
    }
};
