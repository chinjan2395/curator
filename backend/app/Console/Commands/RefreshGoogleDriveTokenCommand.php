<?php

namespace App\Console\Commands;

use App\Models\GoogleDriveConnection;
use App\Services\Storage\GoogleDriveTokenService;
use Illuminate\Console\Command;

class RefreshGoogleDriveTokenCommand extends Command
{
    protected $signature = 'google-drive:refresh-token';

    protected $description = 'Refresh Google Drive OAuth access token and update connection health';

    public function handle(GoogleDriveTokenService $tokens): int
    {
        $connection = GoogleDriveConnection::current();

        if (! $connection) {
            $this->info('No in-app Google Drive connection configured.');

            return self::SUCCESS;
        }

        if ($tokens->refreshConnectionHealth($connection)) {
            $this->info('Google Drive token refreshed successfully.');

            return self::SUCCESS;
        }

        $connection->refresh();
        $this->warn('Google Drive token refresh failed: '.($connection->last_error ?? 'unknown error'));

        return self::SUCCESS;
    }
}
