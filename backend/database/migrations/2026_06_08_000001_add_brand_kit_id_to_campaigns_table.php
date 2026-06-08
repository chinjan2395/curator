<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('brand_kit_id')
                ->nullable()
                ->after('ai_strategy')
                ->constrained('brand_kits')
                ->nullOnDelete();

            $table->foreignId('template_id')
                ->nullable()
                ->after('brand_kit_id')
                ->constrained('content_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_kit_id');
            $table->dropConstrainedForeignId('template_id');
        });
    }
};
