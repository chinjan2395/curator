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
}
