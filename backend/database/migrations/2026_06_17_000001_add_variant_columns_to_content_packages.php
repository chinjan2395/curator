<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_packages', function (Blueprint $table): void {
            // Groups all variants of the same original package together (UUID string)
            $table->string('variant_group_id', 36)->nullable()->index()->after('parent_id');
            // 0 = original, 1 = Variant A, 2 = Variant B, 3 = Variant C
            $table->unsignedTinyInteger('variant_index')->default(0)->after('variant_group_id');
            // Flagged when user picks this variant as the winner
            $table->boolean('is_winner')->default(false)->after('variant_index');
        });
    }

    public function down(): void
    {
        Schema::table('content_packages', function (Blueprint $table): void {
            $table->dropIndex(['variant_group_id']);
            $table->dropColumn(['variant_group_id', 'variant_index', 'is_winner']);
        });
    }
};
