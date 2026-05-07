<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (! Schema::hasColumn('feeds', 'account_avatar_url')) {
                $table->text('account_avatar_url')->nullable()->after('source_account_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (Schema::hasColumn('feeds', 'account_avatar_url')) {
                $table->dropColumn('account_avatar_url');
            }
        });
    }
};
