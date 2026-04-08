<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_app_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 64);
            $table->string('client_id', 512);
            $table->text('client_secret_encrypted');
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_app_configs');
    }
};

