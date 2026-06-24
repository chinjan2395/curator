<?php

namespace App\Support;

class NavigationMenuRegistry
{
    public const SETTINGS_KEY = 'navigation';

    /**
     * @return array<string, array{id: string, label: string, section: string, route_prefixes?: list<string>}>
     */
    public static function menuDefinitions(): array
    {
        return [
            'integrations' => [
                'id' => 'integrations',
                'label' => 'Integrations',
                'section' => 'connect',
                'route_prefixes' => ['/credentials', '/integrations'],
            ],
            'curator' => [
                'id' => 'curator',
                'label' => 'Curator',
                'section' => 'content',
                'route_prefixes' => ['/curator'],
            ],
            'campaigns' => [
                'id' => 'campaigns',
                'label' => 'Campaigns',
                'section' => 'content',
                'route_prefixes' => ['/campaigns'],
            ],
            'schedule' => [
                'id' => 'schedule',
                'label' => 'Schedule',
                'section' => 'content',
                'route_prefixes' => ['/calendar', '/publisher'],
            ],
            'content-library' => [
                'id' => 'content-library',
                'label' => 'Content Library',
                'section' => 'content',
                'route_prefixes' => ['/content-library', '/content'],
            ],
            'analytics' => [
                'id' => 'analytics',
                'label' => 'Analytics',
                'section' => 'insights',
                'route_prefixes' => ['/analytics'],
            ],
            'inbox' => [
                'id' => 'inbox',
                'label' => 'Inbox',
                'section' => 'insights',
                'route_prefixes' => ['/inbox'],
            ],
            'notifications' => [
                'id' => 'notifications',
                'label' => 'Notifications',
                'section' => 'insights',
                'route_prefixes' => ['/notifications'],
            ],
            'oauth-apps' => [
                'id' => 'oauth-apps',
                'label' => 'OAuth Apps',
                'section' => 'administration',
                'route_prefixes' => ['/oauth-apps'],
            ],
            'admin-users' => [
                'id' => 'admin-users',
                'label' => 'Users',
                'section' => 'administration',
                'route_prefixes' => ['/admin/users'],
            ],
            'admin-sync-ops' => [
                'id' => 'admin-sync-ops',
                'label' => 'Sync Ops',
                'section' => 'administration',
                'route_prefixes' => ['/admin/sync-ops'],
            ],
            'admin-system' => [
                'id' => 'admin-system',
                'label' => 'System',
                'section' => 'administration',
                'route_prefixes' => ['/admin/system'],
            ],
            'admin-trends' => [
                'id' => 'admin-trends',
                'label' => 'Trends',
                'section' => 'administration',
                'route_prefixes' => ['/admin/trends'],
            ],
            'admin-moderation' => [
                'id' => 'admin-moderation',
                'label' => 'Moderation',
                'section' => 'administration',
                'route_prefixes' => ['/admin/moderation'],
            ],
            'admin-activity' => [
                'id' => 'admin-activity',
                'label' => 'Activity',
                'section' => 'administration',
                'route_prefixes' => ['/admin/activity'],
            ],
            'admin-dev-tools' => [
                'id' => 'admin-dev-tools',
                'label' => 'Dev Tools',
                'section' => 'administration',
                'route_prefixes' => ['/admin/dev-tools'],
            ],
        ];
    }

    /**
     * @return array<string, array{id: string, label: string, section: string}>
     */
    public static function featureDefinitions(): array
    {
        return [
            'publish_brand_kit' => [
                'id' => 'publish_brand_kit',
                'label' => 'Publish brand kit import',
                'section' => 'features',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function menuIds(): array
    {
        return array_keys(self::menuDefinitions());
    }

    /**
     * @return list<string>
     */
    public static function featureIds(): array
    {
        return array_keys(self::featureDefinitions());
    }

    /**
     * @return array{menus: array<string, bool>, features: array<string, bool>}
     */
    public static function defaultSettings(): array
    {
        $menus = [];
        foreach (self::menuIds() as $id) {
            $menus[$id] = true;
        }

        $features = [];
        foreach (self::featureIds() as $id) {
            $features[$id] = true;
        }

        return [
            'menus' => $menus,
            'features' => $features,
        ];
    }

    public static function routeMatches(string $menuId, string $path): bool
    {
        $definition = self::menuDefinitions()[$menuId] ?? null;
        if (! $definition) {
            return false;
        }

        foreach ($definition['route_prefixes'] ?? [] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
