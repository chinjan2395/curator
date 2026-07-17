<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstUserSuperadminTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registered_user_becomes_superadmin(): void
    {
        $this->assertSame(0, User::query()->count());

        $response = $this->postJson('/api/register', [
            'name' => 'Founder',
            'email' => 'founder@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', User::ROLE_SUPERADMIN);

        $this->assertDatabaseHas('users', [
            'email' => 'founder@example.com',
            'role' => User::ROLE_SUPERADMIN,
        ]);
    }

    public function test_subsequent_registered_users_remain_regular_users(): void
    {
        User::factory()->superadmin()->create([
            'email' => 'founder@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', User::ROLE_USER);

        $this->assertDatabaseHas('users', [
            'email' => 'member@example.com',
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_role_for_new_registration_reflects_existing_users(): void
    {
        $this->assertSame(User::ROLE_SUPERADMIN, User::roleForNewRegistration());

        User::factory()->create();

        $this->assertSame(User::ROLE_USER, User::roleForNewRegistration());
    }
}
