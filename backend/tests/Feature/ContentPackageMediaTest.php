<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
