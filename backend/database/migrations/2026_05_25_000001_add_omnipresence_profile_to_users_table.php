<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('email');
            $table->string('industry', 100)->nullable()->after('avatar_url');
            $table->text('target_audience')->nullable()->after('industry');
            $table->text('brand_voice')->nullable()->after('target_audience');
            $table->boolean('is_onboarded')->default(false)->after('brand_voice');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'industry', 'target_audience', 'brand_voice', 'is_onboarded']);
        });
    }
};
