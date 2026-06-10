<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embed_post_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->text('target_url')->nullable();
            $table->text('page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['post_id', 'created_at']);
            $table->index(['workspace_id', 'post_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embed_post_events');
    }
};
