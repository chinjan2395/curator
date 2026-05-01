<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (! Schema::hasColumn('feeds', 'source_account_label')) {
                $table->string('source_account_label')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            if (Schema::hasColumn('feeds', 'source_account_label')) {
                $table->dropColumn('source_account_label');
            }
        });
    }
};
