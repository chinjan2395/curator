<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_list_campaigns(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/campaigns', [
            'name' => 'Launch',
            'platforms' => ['twitter', 'instagram'],
            'tone' => 'professional',
        ]);

        $create->assertCreated()->assertJsonPath('data.name', 'Launch');

        $this->getJson('/api/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_list_and_update_campaign_with_brand_kit_attached(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $brandKit = $this->postJson('/api/content/brand-kits', [
            'name' => 'Acme Brand',
            'colors' => ['primary' => '#123456'],
        ])->assertCreated()->json('data');

        $create = $this->postJson('/api/campaigns', [
            'name' => 'Launch',
            'platforms' => ['twitter'],
            'brand_kit_id' => $brandKit['id'],
        ]);

        $create->assertCreated()->assertJsonPath('data.brand_kit.colors.primary', '#123456');

        $this->getJson('/api/campaigns')
            ->assertOk()
            ->assertJsonPath('data.0.brand_kit.colors.primary', '#123456');

        $campaignId = $create->json('data.id');

        $this->getJson("/api/campaigns/{$campaignId}")
            ->assertOk()
            ->assertJsonPath('data.brand_kit.colors.primary', '#123456');

        $this->putJson("/api/campaigns/{$campaignId}", ['name' => 'Launch v2'])
            ->assertOk()
            ->assertJsonPath('data.brand_kit.colors.primary', '#123456');
    }
}
