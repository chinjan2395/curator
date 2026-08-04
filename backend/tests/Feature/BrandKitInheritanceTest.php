<?php

namespace Tests\Feature;

use App\Models\BrandKit;
use App\Models\User;
use App\Models\Workspace;
use App\Support\BrandKitExpandedSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandKitInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_inherits_and_can_override_and_reset_from_master(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $master = $this->postJson('/api/content/brand-kits', [
            'name' => 'Master Brand',
            'colors' => ['primary' => '#111111'],
        ])->assertCreated()->json('data');

        $child = $this->postJson("/api/content/brand-kits/{$master['id']}/create-child", [
            'name' => 'Seasonal Child',
        ])->assertCreated()->json('data');

        $this->assertSame($master['id'], $child['parent_id']);
        $this->assertSame('#111111', $child['colors']['primary']);

        // Override a single nested path on the child.
        $this->patchJson("/api/content/brand-kits/{$child['id']}/overrides", [
            'feed_colors' => ['post_border' => ['color' => '#C8102E']],
        ])->assertOk()
            ->assertJsonPath('data.resolved.feed_colors.post_border.color', '#c8102e')
            ->assertJsonPath('data.overridden_paths', ['feed_colors.post_border.color']);

        $resolved = $this->getJson("/api/content/brand-kits/{$child['id']}/resolved")->assertOk()->json('data');
        $this->assertSame('#c8102e', $resolved['resolved']['feed_colors']['post_border']['color']);
        $this->assertSame(['feed_colors.post_border.color'], $resolved['overridden_paths']);

        // Reset the override -> falls back to Master's (default) value.
        $this->postJson("/api/content/brand-kits/{$child['id']}/reset", [
            'path' => 'feed_colors.post_border.color',
        ])->assertOk()
            ->assertJsonPath('data.overridden_paths', []);

        $resolvedAfterReset = $this->getJson("/api/content/brand-kits/{$child['id']}/resolved")
            ->assertOk()->json('data.resolved');
        $this->assertSame('#e2e8f0', $resolvedAfterReset['feed_colors']['post_border']['color']);

        // Live cascade: changing the Master's own overrides propagates to the
        // still-unmodified child.
        $this->patchJson("/api/content/brand-kits/{$master['id']}/overrides", [
            'feed_colors' => ['post_border' => ['color' => '#00ff00']],
        ])->assertOk();

        $childFresh = BrandKit::find($child['id']);
        $this->assertSame('#00ff00', $childFresh->resolve()['feed_colors']['post_border']['color']);
    }

    public function test_store_rejects_grandchild_parent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $master = $this->postJson('/api/content/brand-kits', ['name' => 'Master'])
            ->assertCreated()->json('data');
        $child = $this->postJson("/api/content/brand-kits/{$master['id']}/create-child", ['name' => 'Child'])
            ->assertCreated()->json('data');

        $this->postJson('/api/content/brand-kits', [
            'name' => 'Grandchild',
            'parent_id' => $child['id'],
        ])->assertStatus(422);

        $this->postJson("/api/content/brand-kits/{$child['id']}/create-child", [
            'name' => 'Grandchild',
        ])->assertStatus(422);
    }

    public function test_destroy_returns_422_when_kit_has_children(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $master = $this->postJson('/api/content/brand-kits', ['name' => 'Master'])
            ->assertCreated()->json('data');
        $this->postJson("/api/content/brand-kits/{$master['id']}/create-child", ['name' => 'Child'])
            ->assertCreated();

        $this->deleteJson("/api/content/brand-kits/{$master['id']}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a brand kit that has child kits — reassign or delete them first.');

        $this->assertNotNull(BrandKit::find($master['id']));
    }

    public function test_applying_brand_kit_to_workspace_syncs_publish_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test WS',
        ]);

        $master = $this->postJson('/api/content/brand-kits', ['name' => 'Master'])
            ->assertCreated()->json('data');
        $child = $this->postJson("/api/content/brand-kits/{$master['id']}/create-child", ['name' => 'Child'])
            ->assertCreated()->json('data');
        $this->patchJson("/api/content/brand-kits/{$child['id']}/overrides", [
            'feed_colors' => ['post_border' => ['color' => '#123456']],
        ])->assertOk();

        $childModel = BrandKit::find($child['id']);
        $expectedSettings = BrandKitExpandedSettings::mapToPublishSettings($childModel->resolve());

        $applyResponse = $this->postJson("/api/workspaces/{$workspace->id}/publish/brand-kit", [
            'brand_kit_id' => $child['id'],
        ])->assertOk();

        $applyResponse->assertJsonPath('data.brand_kit_id', $child['id']);
        $applyResponse->assertJsonPath('data.brand_kit_synced', true);

        $workspace->refresh();
        $this->assertSame($child['id'], $workspace->brand_kit_id);
        $this->assertSame($expectedSettings['colors']['post_border']['color'], $workspace->publish_settings['colors']['post_border']['color']);

        $stats = $this->getJson("/api/workspaces/{$workspace->id}/publish/stats")->assertOk();
        $stats->assertJsonPath('data.brand_kit_synced', true);

        // Simulate a manual Publish-page edit that drifts away from the kit.
        $this->putJson("/api/workspaces/{$workspace->id}/publish/settings", [
            'publish_settings' => ['colors' => ['post_border' => ['color' => '#ffffff']]],
        ])->assertOk();

        $statsAfterDrift = $this->getJson("/api/workspaces/{$workspace->id}/publish/stats")->assertOk();
        $statsAfterDrift->assertJsonPath('data.brand_kit_synced', false);
    }
}
