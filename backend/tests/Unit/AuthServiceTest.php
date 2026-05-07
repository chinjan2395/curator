<?php

namespace Tests\Unit;

use App\DTOs\AuthData;
use App\Models\User;
use App\Services\AuthService;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_attempt_login_returns_null_when_credentials_invalid(): void
    {
        Mockery::mock('alias:Illuminate\Support\Facades\Auth')
            ->shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $service = new AuthService();
        $this->assertNull($service->attemptLogin('bad@example.com', 'wrong'));
    }

    public function test_register_user_creates_user_and_token(): void
    {
        $tokenObject = new class
        {
            public string $plainTextToken = 'plain_token';
        };

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('createToken')->once()->with('auth')->andReturn($tokenObject);

        Mockery::mock('alias:App\Models\User')
            ->shouldReceive('create')
            ->once()
            ->andReturn($user);

        $service = new AuthService();
        $dto = AuthData::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
        ]);

        $result = $service->registerUser($dto);
        $this->assertSame('plain_token', $result['token']);
        $this->assertSame($user, $result['user']);
    }
}
