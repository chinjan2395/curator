<?php

namespace Tests\Feature;

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
            'colors' => ['primary' => '#000'],
        ]);
        $create->assertCreated();

        $id = $create->json('data.id');
        $this->getJson('/api/content/brand-kits')->assertOk()->assertJsonPath('data.0.name', 'Acme Brand');

        $this->putJson("/api/content/brand-kits/{$id}", ['name' => 'Acme Updated'])->assertOk();
        $this->deleteJson("/api/content/brand-kits/{$id}")->assertOk();
    }
}
