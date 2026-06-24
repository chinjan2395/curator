<?php

namespace App\Services;

use App\Repositories\NavigationSettingsRepository;
use App\Support\NavigationMenuRegistry;
use InvalidArgumentException;

class NavigationSettingsService
{
    public function __construct(
        private readonly NavigationSettingsRepository $repository,
    ) {}

    /**
     * @return array{menus: array<string, bool>, features: array<string, bool>, registry: array{menus: array<string, array>, features: array<string, array>}}
     */
    public function getEffectiveSettings(): array
    {
        $stored = $this->repository->get() ?? [];
        $defaults = NavigationMenuRegistry::defaultSettings();

        return [
            'menus' => $this->mergeBooleanMap($defaults['menus'], $stored['menus'] ?? []),
            'features' => $this->mergeBooleanMap($defaults['features'], $stored['features'] ?? []),
            'registry' => [
                'menus' => NavigationMenuRegistry::menuDefinitions(),
                'features' => NavigationMenuRegistry::featureDefinitions(),
            ],
        ];
    }

    /**
     * @param  array{menus?: array<string, bool>, features?: array<string, bool>}  $payload
     * @return array{menus: array<string, bool>, features: array<string, bool>, registry: array{menus: array<string, array>, features: array<string, array>}}
     */
    public function update(array $payload): array
    {
        $current = $this->getEffectiveSettings();
        $menus = $current['menus'];
        $features = $current['features'];

        if (array_key_exists('menus', $payload)) {
            $menus = $this->applyBooleanUpdates(
                $menus,
                $payload['menus'],
                NavigationMenuRegistry::menuIds(),
                'menu',
            );
        }

        if (array_key_exists('features', $payload)) {
            $features = $this->applyBooleanUpdates(
                $features,
                $payload['features'],
                NavigationMenuRegistry::featureIds(),
                'feature',
            );
        }

        $this->repository->put([
            'menus' => $menus,
            'features' => $features,
        ]);

        return $this->getEffectiveSettings();
    }

    public function isMenuEnabled(string $menuId): bool
    {
        $settings = $this->getEffectiveSettings();

        return (bool) ($settings['menus'][$menuId] ?? true);
    }

    public function isFeatureEnabled(string $featureId): bool
    {
        $settings = $this->getEffectiveSettings();

        return (bool) ($settings['features'][$featureId] ?? true);
    }

    /**
     * @param  array<string, bool>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, bool>
     */
    private function mergeBooleanMap(array $base, array $overrides): array
    {
        $merged = $base;
        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $base)) {
                $merged[$key] = (bool) $value;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, bool>  $current
     * @param  array<string, mixed>  $updates
     * @param  list<string>  $allowedIds
     * @return array<string, bool>
     */
    private function applyBooleanUpdates(array $current, array $updates, array $allowedIds, string $type): array
    {
        $next = $current;

        foreach ($updates as $key => $value) {
            if (! in_array($key, $allowedIds, true)) {
                throw new InvalidArgumentException("Unknown {$type} key: {$key}");
            }
            $next[$key] = (bool) $value;
        }

        return $next;
    }
}
