<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->foreignId('logo_asset_id')
                ->nullable()
                ->after('logo_url')
                ->constrained('assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logo_asset_id');
        });
    }
};
