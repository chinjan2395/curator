<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationMenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NavigationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_settings_returns_defaults_for_authenticated_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/navigation-settings');

        $response->assertOk()
            ->assertJsonPath('data.menus.curator', true)
            ->assertJsonPath('data.menus.campaigns', true)
            ->assertJsonPath('data.features.publish_brand_kit', true);
    }

    public function test_navigation_settings_requires_auth(): void
    {
        $this->getJson('/api/navigation-settings')->assertUnauthorized();
    }

    public function test_admin_can_update_navigation_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->putJson('/api/admin/navigation-settings', [
            'menus' => [
                'campaigns' => false,
                'inbox' => false,
            ],
            'features' => [
                'publish_brand_kit' => false,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.menus.campaigns', false)
            ->assertJsonPath('data.menus.inbox', false)
            ->assertJsonPath('data.features.publish_brand_kit', false)
            ->assertJsonPath('data.menus.curator', true);

        $this->getJson('/api/navigation-settings')
            ->assertOk()
            ->assertJsonPath('data.menus.campaigns', false)
            ->assertJsonPath('data.features.publish_brand_kit', false);
    }

    public function test_non_admin_cannot_update_navigation_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/admin/navigation-settings', [
            'menus' => ['campaigns' => false],
        ])->assertForbidden();
    }

    public function test_admin_update_ignores_unknown_keys(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->putJson('/api/admin/navigation-settings', [
            'menus' => [
                'campaigns' => false,
                'unknown-menu' => false,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.menus.campaigns', false);

        $menus = $response->json('data.menus');
        $this->assertArrayNotHasKey('unknown-menu', $menus);
        $this->assertSame(count(NavigationMenuRegistry::menuIds()), count($menus));
    }

    public function test_admin_show_includes_registry_metadata(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/navigation-settings')
            ->assertOk()
            ->assertJsonPath('data.registry.menus.campaigns.label', 'Campaigns')
            ->assertJsonPath('data.registry.features.publish_brand_kit.label', 'Publish brand kit import');
    }
}
