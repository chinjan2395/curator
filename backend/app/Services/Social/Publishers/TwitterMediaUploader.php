<?php

namespace App\Services\Social\Publishers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwitterMediaUploader
{
    /**
     * Upload image bytes for attachment to a tweet (X API v2).
     */
    public function uploadImage(string $accessToken, string $imageBinary): string
    {
        $response = Http::withToken($accessToken)
            ->attach('media', $imageBinary, 'upload.jpg')
            ->post('https://api.x.com/2/media/upload', [
                'media_category' => 'tweet_image',
            ]);

        if (! $response->ok()) {
            $detail = $response->json('detail')
                ?? $response->json('title')
                ?? $response->json('errors.0.message')
                ?? $response->body();

            throw new RuntimeException('X media upload failed: '.$detail);
        }

        $mediaId = (string) ($response->json('data.id') ?? $response->json('media_id_string') ?? '');
        if ($mediaId === '') {
            throw new RuntimeException('X media upload returned no media id.');
        }

        return $mediaId;
    }

    public function uploadFromUrl(string $accessToken, string $imageUrl): string
    {
        $imageResponse = Http::timeout(30)->get($imageUrl);
        if (! $imageResponse->ok()) {
            throw new RuntimeException('Could not download image for tweet attachment.');
        }

        return $this->uploadImage($accessToken, $imageResponse->body());
    }
}
