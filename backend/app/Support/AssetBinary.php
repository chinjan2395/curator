<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

/**
 * Reads an asset's bytes back off whichever disk actually holds it.
 *
 * Mirrors the disk-walking AssetController::file() does when streaming, for
 * callers that need the content in memory rather than as a response.
 */
class AssetBinary
{
    /**
     * @return array{content: string, mime: string}|null null when no disk holds the file
     */
    public static function read(Asset $asset): ?array
    {
        if (! $asset->storage_path) {
            return null;
        }

        foreach ($asset->candidateStorageDisks() as $disk) {
            $storage = Storage::disk($disk);

            try {
                if (! $storage->exists($asset->storage_path)) {
                    continue;
                }

                $content = $storage->get($asset->storage_path);
                if ($content === null || $content === '') {
                    continue;
                }

                return [
                    'content' => $content,
                    'mime' => $asset->mime_type ?: (string) $storage->mimeType($asset->storage_path),
                ];
            } catch (\Throwable) {
                // A misconfigured disk should not mask a working one later in the list.
                continue;
            }
        }

        return null;
    }
}
