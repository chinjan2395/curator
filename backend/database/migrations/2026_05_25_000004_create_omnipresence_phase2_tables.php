<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('product_info')->nullable();
            $table->json('target_audience')->nullable();
            $table->string('tone', 50)->nullable();
            $table->json('goals')->nullable();
            $table->json('platforms')->nullable();
            $table->string('status', 50)->default('draft');
            $table->json('ai_strategy')->nullable();
            $table->timestamps();
        });

        Schema::create('content_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 50);
            $table->string('content_type', 50);
            $table->text('caption')->nullable();
            $table->json('media_urls')->nullable();
            $table->json('hashtags')->nullable();
            $table->json('platform_specific_data')->nullable();
            $table->float('ai_score')->nullable();
            $table->string('status', 50)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('content_packages')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('storage_path');
            $table->string('thumbnail_path')->nullable();
            $table->json('ai_tags')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('brand_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Default');
            $table->string('logo_url')->nullable();
            $table->json('colors')->nullable();
            $table->json('fonts')->nullable();
            $table->json('watermark')->nullable();
            $table->timestamps();
        });

        Schema::create('content_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 50)->nullable();
            $table->string('content_type', 50)->nullable();
            $table->json('template_data');
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('name');
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('social_credential_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamp('published_at')->nullable();
            $table->string('platform_post_id')->nullable();
            $table->string('platform_post_url')->nullable();
            $table->string('status', 50)->default('scheduled');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50);
            $table->foreignId('content_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 50)->nullable();
            $table->string('content_type', 50)->nullable();
            $table->text('modification_diff')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('trend_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('push')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'event_type']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_credential_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 50);
            $table->string('message_type', 50);
            $table->string('external_id')->nullable();
            $table->string('author_name')->nullable();
            $table->text('body')->nullable();
            $table->string('post_url')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('trend_snapshots');
        Schema::dropIfExists('learning_signals');
        Schema::dropIfExists('scheduled_posts');
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('content_templates');
        Schema::dropIfExists('brand_kits');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('content_packages');
        Schema::dropIfExists('campaigns');
    }
};
