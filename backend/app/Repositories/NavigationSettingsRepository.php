<?php

namespace App\Repositories;

use App\Models\PlatformSetting;
use App\Support\NavigationMenuRegistry;

class NavigationSettingsRepository
{
    public function get(): ?array
    {
        $row = PlatformSetting::query()
            ->where('key', NavigationMenuRegistry::SETTINGS_KEY)
            ->first();

        return $row?->value;
    }

    public function put(array $value): array
    {
        $row = PlatformSetting::query()->updateOrCreate(
            ['key' => NavigationMenuRegistry::SETTINGS_KEY],
            ['value' => $value],
        );

        return $row->value;
    }
}
