<?php

namespace Tests\Feature;

use App\Models\BrandKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_crud_brand_kit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/content/brand-kits', [
            'name' => 'Acme Brand',
            'colors' => ['primary' => '#000000'],
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.is_default', true);

        $id = $create->json('data.id');
        $this->getJson('/api/content/brand-kits')->assertOk()->assertJsonPath('data.0.name', 'Acme Brand');
        $this->getJson("/api/content/brand-kits/{$id}")->assertOk()->assertJsonPath('data.name', 'Acme Brand');

        $this->putJson("/api/content/brand-kits/{$id}", [
            'name' => 'Acme Updated',
            'colors' => ['primary' => '#111111'],
        ])->assertOk()->assertJsonPath('data.colors.primary', '#111111');

        $this->deleteJson("/api/content/brand-kits/{$id}")->assertOk();
    }

    public function test_only_one_default_kit_per_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/content/brand-kits', ['name' => 'First'])->assertCreated();
        $second = $this->postJson('/api/content/brand-kits', ['name' => 'Second'])->assertCreated();

        $firstId = $first->json('data.id');
        $secondId = $second->json('data.id');

        $this->assertTrue($first->json('data.is_default'));
        $this->assertFalse($second->json('data.is_default'));

        $this->putJson("/api/content/brand-kits/{$secondId}", ['is_default' => true])->assertOk();

        $this->assertFalse(BrandKit::find($firstId)->is_default);
        $this->assertTrue(BrandKit::find($secondId)->is_default);
    }

    public function test_deleting_default_promotes_another_kit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/content/brand-kits', ['name' => 'Alpha'])->assertCreated();
        $second = $this->postJson('/api/content/brand-kits', ['name' => 'Beta'])->assertCreated();

        $this->deleteJson('/api/content/brand-kits/'.$first->json('data.id'))->assertOk();

        $this->assertTrue(BrandKit::find($second->json('data.id'))->is_default);
    }
}
