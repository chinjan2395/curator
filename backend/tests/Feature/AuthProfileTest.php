<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_alias_returns_user(): void
    {
        $user = User::factory()->create(['is_onboarded' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_auth_me_auto_verifies_when_email_verification_disabled(): void
    {
        config(['auth.require_email_verification' => false]);

        $user = User::factory()->unverified()->create(['is_onboarded' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email_verification_required', false)
            ->assertJsonPath('data.email_verified_at', fn ($value) => $value !== null);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_profile_update_sets_onboarding_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'industry' => 'SaaS',
            'target_audience' => 'Founders',
            'brand_voice' => 'Professional',
            'is_onboarded' => true,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'industry' => 'SaaS',
            'is_onboarded' => true,
        ]);
    }
}
