<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetupStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_status_returns_expected_keys(): void
    {
        $user = User::factory()->create(['is_onboarded' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/setup/status');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'is_onboarded',
                    'has_social_credentials',
                    'has_workspaces',
                    'has_feeds',
                    'has_synced_posts',
                    'has_campaigns',
                    'has_approved_packages',
                    'has_scheduled_posts',
                ],
            ])
            ->assertJsonPath('data.is_onboarded', false);
    }

    public function test_setup_status_requires_auth(): void
    {
        $this->getJson('/api/setup/status')->assertUnauthorized();
    }
}
