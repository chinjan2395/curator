<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('public_key', 64)->nullable()->unique()->after('owner_id');
            $table->json('publish_settings')->nullable()->after('public_key');
            $table->timestamp('last_published_at')->nullable()->after('publish_settings');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['last_published_at', 'publish_settings', 'public_key']);
        });
    }
};
