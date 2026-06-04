<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_returns_ai_and_publish_matrix(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/capabilities');

        $response->assertOk()
            ->assertJsonPath('data.ai.driver', config('services.ai.driver', 'stub'))
            ->assertJsonPath('data.publish.native.twitter.enabled', true)
            ->assertJsonPath('data.publish.native.tiktok.enabled', true)
            ->assertJsonPath('data.publish.native.threads.enabled', true)
            ->assertJsonPath('data.publish.native.linkedin.enabled', true)
            ->assertJsonPath('data.inbox.sync_mode', 'stub');
    }

    public function test_capabilities_requires_auth(): void
    {
        $this->getJson('/api/capabilities')->assertUnauthorized();
    }
}
