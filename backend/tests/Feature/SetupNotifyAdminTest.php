<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetupNotifyAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_it_notifies_every_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $superadmin = User::factory()->superadmin()->create();
        User::factory()->create(); // a plain member should not be notified
        $member = User::factory()->create();

        Sanctum::actingAs($member);

        $this->postJson('/api/setup/notify-admin')
            ->assertOk()
            ->assertJsonPath('data.throttled', false)
            ->assertJsonPath('data.notified', 2);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'setup_required',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superadmin->id,
            'type' => 'setup_required',
        ]);
        $this->assertSame(2, Notification::query()->where('type', 'setup_required')->count());
    }

    public function test_a_second_request_the_same_day_is_throttled(): void
    {
        User::factory()->admin()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/setup/notify-admin')->assertOk()->assertJsonPath('data.throttled', false);
        $this->postJson('/api/setup/notify-admin')->assertOk()->assertJsonPath('data.throttled', true);

        $this->assertSame(1, Notification::query()->where('type', 'setup_required')->count());
    }

    public function test_an_admin_asking_does_not_notify_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/setup/notify-admin')->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'setup_required',
        ]);
    }

    public function test_it_requires_auth(): void
    {
        $this->postJson('/api/setup/notify-admin')->assertUnauthorized();
    }
}
