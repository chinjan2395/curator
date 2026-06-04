<?php

namespace App\Console\Commands;

use App\Models\SocialCredential;
use Illuminate\Console\Command;

class RefreshSocialTokensCommand extends Command
{
    protected $signature = 'social:refresh-tokens';

    protected $description = 'Refresh expiring social OAuth tokens';

    public function handle(): int
    {
        $credentials = SocialCredential::query()
            ->where('status', 'active')
            ->whereIn('provider', ['youtube', 'twitter', 'tiktok', 'threads', 'linkedin'])
            ->get();

        $refreshed = 0;
        $failed = 0;

        foreach ($credentials as $credential) {
            try {
                $token = $credential->getValidAccessToken();
                $credential->refreshTokenHealth();
                $credential->save();
                if ($token) {
                    $refreshed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $credential->token_health = 'error';
                $credential->save();
                $failed++;
                $this->warn("{$credential->provider}#{$credential->id}: {$e->getMessage()}");
            }
        }

        $this->info("Token refresh complete: {$refreshed} ok, {$failed} need attention.");

        return self::SUCCESS;
    }
}
