<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('brand_kits')
                ->restrictOnDelete();
            $table->json('overrides')->nullable()->after('watermark');
        });

        DB::table('brand_kits')->orderBy('id')->chunkById(100, function ($kits) {
            foreach ($kits as $kit) {
                $overrides = [
                    'colors' => json_decode((string) $kit->colors, true) ?? [],
                    'fonts' => json_decode((string) $kit->fonts, true) ?? [],
                    'watermark' => json_decode((string) $kit->watermark, true) ?? [],
                ];

                DB::table('brand_kits')
                    ->where('id', $kit->id)
                    ->update(['overrides' => json_encode($overrides)]);
            }
        });

        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn(['colors', 'fonts', 'watermark']);
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->json('colors')->nullable();
            $table->json('fonts')->nullable();
            $table->json('watermark')->nullable();
        });

        DB::table('brand_kits')->orderBy('id')->chunkById(100, function ($kits) {
            foreach ($kits as $kit) {
                $overrides = json_decode((string) $kit->overrides, true) ?? [];

                DB::table('brand_kits')
                    ->where('id', $kit->id)
                    ->update([
                        'colors' => isset($overrides['colors']) ? json_encode($overrides['colors']) : null,
                        'fonts' => isset($overrides['fonts']) ? json_encode($overrides['fonts']) : null,
                        'watermark' => isset($overrides['watermark']) ? json_encode($overrides['watermark']) : null,
                    ]);
            }
        });

        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('overrides');
        });
    }
};
