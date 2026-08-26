<?php

namespace Tests\Unit;

use App\Models\AiProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderCredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_stored_encrypted_with_a_last_four_hint(): void
    {
        $user = User::factory()->create();

        $credential = AiProviderCredential::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key' => 'sk-super-secret-1234',
        ]);

        $raw = $credential->getAttributes();
        $this->assertArrayNotHasKey('api_key', $raw);
        $this->assertNotEmpty($raw['api_key_encrypted'] ?? null);
        $this->assertStringNotContainsString('sk-super-secret-1234', (string) $raw['api_key_encrypted']);
        $this->assertSame('1234', $raw['key_last_four'] ?? null);

        $this->assertSame('sk-super-secret-1234', $credential->fresh()->api_key);
    }

    public function test_api_key_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create();

        $credential = AiProviderCredential::create([
            'user_id' => $user->id,
            'provider' => 'flux',
            'api_key' => 'bfl-secret-9876',
        ]);

        $serialized = $credential->fresh()->toArray();

        $this->assertArrayNotHasKey('api_key', $serialized);
        $this->assertArrayNotHasKey('api_key_encrypted', $serialized);
        $this->assertSame('9876', $serialized['key_last_four']);
    }

    public function test_undecryptable_key_reads_as_null_instead_of_throwing(): void
    {
        $user = User::factory()->create();

        $credential = AiProviderCredential::create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'api_key' => 'gemini-secret-0001',
        ]);

        // Simulate a rotated APP_KEY leaving the stored ciphertext unreadable.
        $credential->forceFill(['api_key_encrypted' => 'not-valid-ciphertext'])->save();

        $this->assertNull($credential->fresh()->api_key);
    }
}
