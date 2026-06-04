<?php

namespace App\Support;

use App\Models\Asset;
use RuntimeException;

class ContentPackageMediaResolver
{
    /**
     * @param  list<int>  $assetIds
     * @return list<string>
     */
    public function urlsForAssetIds(int $userId, array $assetIds): array
    {
        $ids = array_values(array_unique(array_filter($assetIds, static fn ($id) => is_int($id) || ctype_digit((string) $id))));
        if ($ids === []) {
            return [];
        }

        $assets = Asset::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get();

        if ($assets->count() !== count($ids)) {
            throw new RuntimeException('One or more assets were not found.');
        }

        $urls = [];
        foreach ($assets as $asset) {
            $url = $asset->url;
            if (! is_string($url) || $url === '') {
                throw new RuntimeException('Asset has no public URL. Check APP_URL and storage:link.');
            }
            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * @param  list<string>|null  $existing
     * @param  list<string>|null  $incomingUrls
     * @param  list<int>|null  $assetIds
     * @return list<string>
     */
    public function merge(?array $existing, ?array $incomingUrls, ?array $assetIds, int $userId, int $max = 4): array
    {
        $merged = array_merge(
            is_array($existing) ? $existing : [],
            is_array($incomingUrls) ? $incomingUrls : [],
            $assetIds ? $this->urlsForAssetIds($userId, $assetIds) : [],
        );

        $merged = array_values(array_unique(array_filter($merged, static fn ($u) => is_string($u) && $u !== '')));

        return array_slice($merged, 0, $max);
    }
}
