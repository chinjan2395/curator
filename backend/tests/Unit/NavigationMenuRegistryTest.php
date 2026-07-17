<?php

namespace Tests\Unit;

use App\Support\NavigationMenuRegistry;
use Tests\TestCase;

class NavigationMenuRegistryTest extends TestCase
{
    public function test_default_settings_hide_selected_menus_and_enable_features(): void
    {
        $defaults = NavigationMenuRegistry::defaultSettings();
        $hidden = NavigationMenuRegistry::defaultHiddenMenuIds();

        foreach (NavigationMenuRegistry::menuIds() as $id) {
            $expected = ! in_array($id, $hidden, true);
            $this->assertSame($expected, $defaults['menus'][$id], "menu {$id}");
        }

        foreach (NavigationMenuRegistry::featureIds() as $id) {
            $this->assertTrue($defaults['features'][$id]);
        }
    }

    public function test_route_matches_campaigns_prefix(): void
    {
        $this->assertTrue(NavigationMenuRegistry::routeMatches('campaigns', '/campaigns'));
        $this->assertTrue(NavigationMenuRegistry::routeMatches('campaigns', '/campaigns/42'));
        $this->assertFalse(NavigationMenuRegistry::routeMatches('campaigns', '/calendar'));
    }
}
