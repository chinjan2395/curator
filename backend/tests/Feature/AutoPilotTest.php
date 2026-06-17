<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutoPilotTest extends TestCase
{
    use RefreshDatabase;

    private function makeCampaign(User $user, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Auto-pilot campaign',
            'status' => 'generated',
            'platforms' => ['instagram', 'twitter'],
        ], $overrides));
    }

    public function test_enable_and_disable_auto_pilot(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = $this->makeCampaign($user);

        $this->postJson("/api/campaigns/{$campaign->id}/auto-pilot/enable")
            ->assertOk()
            ->assertJsonPath('data.auto_pilot_enabled', true);

        $this->assertTrue($campaign->fresh()->auto_pilot_enabled);

        $this->postJson("/api/campaigns/{$campaign->id}/auto-pilot/disable")
            ->assertOk()
            ->assertJsonPath('data.auto_pilot_enabled', false);

        $this->assertFalse($campaign->fresh()->auto_pilot_enabled);
    }

    public function test_run_schedules_highest_scored_approved_package_per_platform(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = $this->makeCampaign($user);

        $lowerScore = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Lower score caption',
            'status' => 'approved',
            'ai_score' => 0.55,
        ]);

        $winner = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Winner caption',
            'status' => 'approved',
            'ai_score' => 0.92,
        ]);

        ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'twitter',
            'content_type' => 'post',
            'caption' => 'Twitter draft',
            'status' => 'approved',
            'ai_score' => 0.7,
        ]);

        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'instagram',
            'account_id' => 'acct-1',
            'account_label' => 'Main IG',
            'access_token' => 'token',
            'status' => 'connected',
        ]);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/auto-pilot/run");

        $response->assertOk()
            ->assertJsonPath('data.scheduled.0.platform', 'instagram')
            ->assertJsonPath('data.scheduled.0.content_package_id', $winner->id)
            ->assertJsonCount(1, 'data.scheduled')
            ->assertJsonCount(1, 'data.skipped')
            ->assertJsonPath('data.skipped.0.platform', 'twitter')
            ->assertJsonPath('data.skipped.0.reason', 'no_credential');

        $this->assertDatabaseHas('scheduled_posts', [
            'user_id' => $user->id,
            'content_package_id' => $winner->id,
            'social_credential_id' => $credential->id,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseMissing('scheduled_posts', [
            'content_package_id' => $lowerScore->id,
        ]);

        $this->assertSame('scheduled', $winner->fresh()->status);
        $this->assertSame('approved', $lowerScore->fresh()->status);
        $this->assertSame(1, ScheduledPost::count());
    }

    public function test_run_skips_platform_without_credential(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = $this->makeCampaign($user, ['platforms' => ['linkedin']]);

        ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'linkedin',
            'content_type' => 'post',
            'caption' => 'Approved post',
            'status' => 'approved',
            'ai_score' => 0.8,
        ]);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/auto-pilot/run");

        $response->assertOk()
            ->assertJsonCount(0, 'data.scheduled')
            ->assertJsonCount(1, 'data.skipped')
            ->assertJsonPath('data.skipped.0.reason', 'no_credential');

        $this->assertSame(0, ScheduledPost::count());
    }

    public function test_run_is_forbidden_for_other_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $campaign = $this->makeCampaign($owner);

        Sanctum::actingAs($other);

        $this->postJson("/api/campaigns/{$campaign->id}/auto-pilot/run")
            ->assertForbidden();
    }
}
