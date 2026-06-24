<?php

namespace Tests\Unit;

use App\Support\NavigationMenuRegistry;
use Tests\TestCase;

class NavigationMenuRegistryTest extends TestCase
{
    public function test_default_settings_enable_all_menus_and_features(): void
    {
        $defaults = NavigationMenuRegistry::defaultSettings();

        foreach (NavigationMenuRegistry::menuIds() as $id) {
            $this->assertTrue($defaults['menus'][$id]);
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
