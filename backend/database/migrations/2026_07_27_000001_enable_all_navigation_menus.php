<?php

use App\Models\PlatformSetting;
use App\Support\NavigationMenuRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $row = PlatformSetting::query()
            ->where('key', NavigationMenuRegistry::SETTINGS_KEY)
            ->first();

        $value = $row?->value ?? NavigationMenuRegistry::defaultSettings();

        $menus = [];
        foreach (NavigationMenuRegistry::menuIds() as $id) {
            $menus[$id] = true;
        }

        $features = [];
        foreach (NavigationMenuRegistry::featureIds() as $id) {
            $features[$id] = true;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => NavigationMenuRegistry::SETTINGS_KEY],
            ['value' => array_merge($value, ['menus' => $menus, 'features' => $features])],
        );
    }

    public function down(): void
    {
        // Intentionally left as a no-op: reverting would re-hide modules for
        // whatever admin-chosen state existed at rollback time, which isn't recoverable.
    }
};
