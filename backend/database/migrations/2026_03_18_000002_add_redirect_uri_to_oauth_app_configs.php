<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_app_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('oauth_app_configs', 'redirect_uri')) {
                $table->string('redirect_uri', 1024)->nullable()->after('client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_app_configs', function (Blueprint $table) {
            if (Schema::hasColumn('oauth_app_configs', 'redirect_uri')) {
                $table->dropColumn('redirect_uri');
            }
        });
    }
};

