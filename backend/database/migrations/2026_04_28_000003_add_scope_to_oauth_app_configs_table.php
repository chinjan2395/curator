<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_app_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('oauth_app_configs', 'scope')) {
                $table->string('scope', 32)->default('user')->after('user_id');
            }
        });

        DB::table('oauth_app_configs')
            ->whereNull('scope')
            ->update(['scope' => 'user']);

        Schema::table('oauth_app_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->dropUnique(['user_id', 'provider']);
            $table->unique(['scope', 'user_id', 'provider']);
            $table->index(['scope', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('oauth_app_configs', function (Blueprint $table) {
            $table->dropIndex(['scope', 'provider']);
            $table->dropUnique(['scope', 'user_id', 'provider']);
            $table->unique(['user_id', 'provider']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->dropColumn('scope');
        });
    }
};

