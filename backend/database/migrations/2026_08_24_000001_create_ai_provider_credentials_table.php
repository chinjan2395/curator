<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->text('api_key_encrypted');
            $table->string('key_last_four', 8)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('ai_image_settings')->nullable()->after('ai_prompt_overrides');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_image_settings');
        });

        Schema::dropIfExists('ai_provider_credentials');
    }
};
