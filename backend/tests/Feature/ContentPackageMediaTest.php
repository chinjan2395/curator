<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentPackageMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_media_urls_on_own_package(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'status' => 'draft',
        ]);

        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Hello',
            'status' => 'draft',
        ]);

        $urls = ['https://cdn.example.com/photo.jpg'];

        $this->patchJson("/api/content-packages/{$package->id}/media", [
            'media_urls' => $urls,
        ])
            ->assertOk()
            ->assertJsonPath('data.media_urls', $urls);

        $this->assertSame($urls, $package->fresh()->media_urls);
    }

    public function test_user_cannot_update_other_users_package_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $campaign = Campaign::create([
            'user_id' => $owner->id,
            'name' => 'Owned',
            'status' => 'draft',
        ]);

        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $owner->id,
            'platform' => 'twitter',
            'content_type' => 'post',
            'caption' => 'Private',
            'status' => 'draft',
        ]);

        $this->patchJson("/api/content-packages/{$package->id}/media", [
            'media_urls' => ['https://cdn.example.com/x.jpg'],
        ])->assertForbidden();
    }

    public function test_user_can_attach_library_asset_by_id(): void
    {
        Storage::fake('public');
        config(['app.url' => 'https://curator.test']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'status' => 'draft',
        ]);

        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Hello',
            'status' => 'draft',
            'media_urls' => [],
        ]);

        $path = UploadedFile::fake()->image('hero.jpg')->store('assets/'.$user->id, 'public');
        $asset = Asset::create([
            'user_id' => $user->id,
            'type' => 'image',
            'file_name' => 'hero.jpg',
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'storage_path' => $path,
            'ai_tags' => ['hero', 'image'],
        ]);

        $expectedUrl = 'https://curator.test/storage/'.$path;

        $this->patchJson("/api/content-packages/{$package->id}/media", [
            'asset_ids' => [$asset->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.media_urls.0', $expectedUrl);

        $this->assertSame([$expectedUrl], $package->fresh()->media_urls);
    }

    public function test_asset_upload_stores_ai_tags(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/content/assets', [
            'file' => UploadedFile::fake()->image('promo-banner.png'),
            'type' => 'image',
        ]);

        $response->assertCreated();
        $tags = $response->json('data.ai_tags');
        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
    }

    public function test_approved_packages_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'C',
            'status' => 'draft',
        ]);

        ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'twitter',
            'content_type' => 'post',
            'caption' => 'Approved',
            'status' => 'approved',
        ]);

        ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'facebook',
            'content_type' => 'post',
            'caption' => 'Draft',
            'status' => 'draft',
        ]);

        $this->getJson('/api/content-packages?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
