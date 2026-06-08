<?php

namespace Tests\Feature;

use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_store_persists_scheduled_at_in_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08T12:00:00Z'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'account_id' => 'acct-1',
            'access_token' => 'token',
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $scheduledAt = '2026-06-10T18:30:00Z';

        $response = $this->postJson('/api/schedule', [
            'social_credential_id' => $credential->id,
            'scheduled_at' => $scheduledAt,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $post = ScheduledPost::first();
        $this->assertNotNull($post);
        $this->assertSame('2026-06-10 18:30:00', $post->scheduled_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_calendar_filters_by_utc_range_from_client(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15T12:00:00Z'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'account_id' => 'acct-1',
            'access_token' => 'token',
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $inRange = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'scheduled_at' => Carbon::parse('2026-06-20T10:00:00Z'),
            'status' => 'scheduled',
        ]);

        ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'scheduled_at' => Carbon::parse('2026-07-05T10:00:00Z'),
            'status' => 'scheduled',
        ]);

        $response = $this->getJson('/api/schedule/calendar?'.http_build_query([
            'from' => '2026-06-01T00:00:00.000Z',
            'to' => '2026-06-30T23:59:59.999Z',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$inRange->id], $ids);
    }

    public function test_store_rejects_past_scheduled_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08T12:00:00Z'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'account_id' => 'acct-1',
            'access_token' => 'token',
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $this->postJson('/api/schedule', [
            'social_credential_id' => $credential->id,
            'scheduled_at' => '2026-06-08T11:00:00Z',
        ])->assertUnprocessable();
    }
}
