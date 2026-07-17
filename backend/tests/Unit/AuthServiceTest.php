<?php

namespace Tests\Unit;

use App\DTOs\AuthData;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_attempt_login_returns_null_when_credentials_invalid(): void
    {
        $service = new AuthService();
        $this->assertNull($service->attemptLogin('bad@example.com', 'wrong'));
    }

    public function test_register_user_creates_user_and_token(): void
    {
        $service = new AuthService();
        $dto = AuthData::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $result = $service->registerUser($dto);

        $this->assertNotEmpty($result['token']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertSame('jane@example.com', $result['user']->email);
        $this->assertTrue($result['user']->isSuperAdmin());
    }

    public function test_register_second_user_gets_user_role(): void
    {
        User::factory()->superadmin()->create();

        $service = new AuthService();
        $dto = AuthData::fromArray([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret123',
        ]);

        $result = $service->registerUser($dto);

        $this->assertSame(User::ROLE_USER, $result['user']->role);
        $this->assertFalse($result['user']->isSuperAdmin());
    }
}
