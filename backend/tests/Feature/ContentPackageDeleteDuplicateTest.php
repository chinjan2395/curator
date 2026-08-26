<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentPackageDeleteDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(User $user, array $overrides = []): ContentPackage
    {
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'status' => 'draft',
        ]);

        return ContentPackage::create(array_merge([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Hello',
            'hashtags' => ['#hi'],
            'status' => 'draft',
            'version' => 1,
        ], $overrides));
    }

    public function test_user_can_delete_own_draft(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $package = $this->makePackage($user);

        $this->deleteJson("/api/content-packages/{$package->id}")->assertOk();

        $this->assertNull(ContentPackage::find($package->id));
    }

    public function test_user_cannot_delete_other_users_draft(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $package = $this->makePackage($owner);

        $this->deleteJson("/api/content-packages/{$package->id}")->assertForbidden();
        $this->assertNotNull(ContentPackage::find($package->id));
    }

    public function test_scheduled_package_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $package = $this->makePackage($user, ['status' => 'scheduled']);

        $this->deleteJson("/api/content-packages/{$package->id}")->assertStatus(422);
        $this->assertNotNull(ContentPackage::find($package->id));
    }

    public function test_user_can_duplicate_own_draft_as_new_version(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $package = $this->makePackage($user, ['version' => 2, 'status' => 'approved']);

        $response = $this->postJson("/api/content-packages/{$package->id}/duplicate")->assertOk();

        $duplicateId = $response->json('data.id');
        $duplicate = ContentPackage::find($duplicateId);

        $this->assertNotSame($package->id, $duplicate->id);
        $this->assertSame('draft', $duplicate->status);
        $this->assertSame(3, $duplicate->version);
        $this->assertSame($package->id, $duplicate->parent_id);
        $this->assertSame($package->caption, $duplicate->caption);
    }

    public function test_user_cannot_duplicate_other_users_draft(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $package = $this->makePackage($owner);

        $this->postJson("/api/content-packages/{$package->id}/duplicate")->assertForbidden();
    }
}
