<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makePackage(User $user): ContentPackage
    {
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Visual campaign',
            'status' => 'generated',
            'product_info' => 'Organic skincare line',
            'platforms' => ['instagram'],
        ]);

        return ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Glow naturally with our new serum.',
            'status' => 'draft',
            'version' => 1,
            'ai_score' => 0.8,
        ]);
    }

    public function test_generate_image_creates_asset_and_attaches_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $package = $this->makePackage($user);

        $response = $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'instruction' => 'Minimal product flat lay on marble.',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Image generated and attached.');

        $updated = $response->json('data');
        $this->assertNotEmpty($updated['media_urls']);

        $asset = Asset::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($asset);
        $this->assertSame('image', $asset->type);
        $this->assertSame('public', $asset->storage_disk);
        Storage::disk('public')->assertExists($asset->storage_path);
    }

    public function test_generate_image_rejects_when_media_limit_reached(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $package = $this->makePackage($user);
        $package->update([
            'media_urls' => [
                'https://example.com/1.jpg',
                'https://example.com/2.jpg',
                'https://example.com/3.jpg',
                'https://example.com/4.jpg',
            ],
        ]);

        $this->postJson("/api/content-packages/{$package->id}/generate-image")
            ->assertStatus(422);
    }

    public function test_generate_image_is_forbidden_for_other_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $package = $this->makePackage($owner);

        Sanctum::actingAs($other);

        $this->postJson("/api/content-packages/{$package->id}/generate-image")
            ->assertForbidden();
    }
}
