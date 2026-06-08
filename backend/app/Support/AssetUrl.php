<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Support\Facades\URL;

class AssetUrl
{
    public static function preview(Asset $asset): ?string
    {
        if (! $asset->storage_path) {
            return null;
        }

        if ($asset->storage_disk === 'googledrive') {
            $external = $asset->externalUrl();

            if (is_string($external) && $external !== '') {
                return $external;
            }
        }

        return URL::temporarySignedRoute(
            'content.assets.file',
            now()->addDays(7),
            ['asset' => $asset->id],
            absolute: false,
        );
    }
}
