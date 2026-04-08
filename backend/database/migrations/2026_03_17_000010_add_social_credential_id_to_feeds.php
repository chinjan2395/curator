<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('feeds', 'social_credential_id')) {
            return;
        }

        Schema::table('feeds', function (Blueprint $table) {
            $table->unsignedBigInteger('social_credential_id')->nullable()->after('workspace_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('feeds', 'social_credential_id')) {
            return;
        }

        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn('social_credential_id');
        });
    }
};
