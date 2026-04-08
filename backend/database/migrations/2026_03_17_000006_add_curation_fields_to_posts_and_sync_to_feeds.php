<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->after('external_id');
            $table->boolean('pinned')->default(false)->after('status');
        });

        Schema::table('feeds', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['status', 'pinned']);
        });

        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};

