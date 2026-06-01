<?php

namespace Tests\Unit;

use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialCredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_token_is_stored_encrypted(): void
    {
        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'youtube',
            'account_id' => 'ch1',
            'access_token' => 'secret-access-token',
            'status' => 'active',
        ]);

        $raw = $credential->getAttributes();
        $this->assertNotSame('secret-access-token', $raw['access_token'] ?? '');
        $this->assertSame('', $raw['access_token'] ?? null);
        $this->assertNotEmpty($raw['access_token_encrypted'] ?? null);
        $this->assertSame('secret-access-token', $credential->fresh()->access_token);
    }
}
