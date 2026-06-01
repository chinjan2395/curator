<?php

namespace App\Console\Commands;

use App\Models\SocialCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncSocialMetadataCommand extends Command
{
    protected $signature = 'social:sync-metadata';

    protected $description = 'Sync follower counts and profile metadata for connected accounts';

    public function handle(): int
    {
        $credentials = SocialCredential::query()->where('status', 'active')->get();
        $updated = 0;

        foreach ($credentials as $credential) {
            try {
                $this->syncCredential($credential);
                $credential->last_metadata_synced_at = now();
                $credential->refreshTokenHealth();
                $credential->save();
                $updated++;
            } catch (\Throwable $e) {
                $this->warn("{$credential->provider}#{$credential->id}: {$e->getMessage()}");
            }
        }

        $this->info("Metadata sync complete for {$updated} credentials.");

        return self::SUCCESS;
    }

    private function syncCredential(SocialCredential $credential): void
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return;
        }

        match ($credential->provider) {
            'twitter' => $this->syncTwitter($credential, $token),
            'youtube' => $this->syncYouTube($credential, $token),
            default => null,
        };
    }

    private function syncTwitter(SocialCredential $credential, string $token): void
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.x.com/2/users/me', ['user.fields' => 'public_metrics,profile_image_url']);

        if (! $response->successful()) {
            return;
        }

        $data = $response->json('data', []);
        $credential->profile_image_url = $data['profile_image_url'] ?? $credential->profile_image_url;
        $credential->follower_count = (int) ($data['public_metrics']['followers_count'] ?? $credential->follower_count);
    }

    private function syncYouTube(SocialCredential $credential, string $token): void
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet,statistics',
                'mine' => 'true',
            ]);

        if (! $response->successful()) {
            return;
        }

        $item = $response->json('items.0', []);
        $snippet = $item['snippet'] ?? [];
        $stats = $item['statistics'] ?? [];
        $thumbs = $snippet['thumbnails'] ?? [];
        $credential->profile_image_url = $thumbs['default']['url'] ?? $credential->profile_image_url;
        $credential->follower_count = (int) ($stats['subscriberCount'] ?? $credential->follower_count);
    }
}
