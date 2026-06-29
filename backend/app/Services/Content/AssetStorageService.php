<?php

namespace App\Services\Content;

use App\Models\Asset;
use App\Support\GoogleDriveConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AssetStorageService
{
    public function disk(): string
    {
        return GoogleDriveConfig::isConfigured() ? 'googledrive' : 'public';
    }

    /** @return array{path: string, disk: string} */
    public function storeUploadedFile(UploadedFile $file, int $userId): array
    {
        $disk = $this->disk();
        $directory = 'assets/'.$userId;

        try {
            $path = $file->store($directory, [
                'disk' => $disk,
                'visibility' => $disk === 'public' ? 'public' : 'private',
            ]);
        } catch (\Throwable $e) {
            if ($disk === 'googledrive') {
                Log::error('Google Drive upload failed.', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        return ['path' => $path, 'disk' => $disk];
    }

    /** @return array{path: string, disk: string} */
    public function storeBinary(string $content, string $path): array
    {
        $disk = $this->disk();

        try {
            Storage::disk($disk)->put($path, $content, [
                'visibility' => $disk === 'public' ? 'public' : 'private',
            ]);
        } catch (\Throwable $e) {
            if ($disk === 'googledrive') {
                Log::error('Google Drive upload failed.', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        return ['path' => $path, 'disk' => $disk];
    }

    public function delete(Asset $asset): void
    {
        foreach ($asset->candidateStorageDisks() as $disk) {
            try {
                $storage = Storage::disk($disk);
                if ($storage->exists($asset->storage_path)) {
                    $storage->delete($asset->storage_path);

                    return;
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
