<?php

use App\Models\OAuthAppConfig;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('oauth:promote-user-configs-to-shared {user : User ID or email} {--overwrite : Overwrite existing shared provider configs}', function () {
    $identifier = trim((string) $this->argument('user'));
    $overwrite = (bool) $this->option('overwrite');

    $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
        ? User::query()->where('email', $identifier)->first()
        : User::query()->where('id', $identifier)->first();

    if (! $user) {
        $this->error("User not found for '{$identifier}'. Use a valid user ID or email.");

        return 1;
    }

    $sourceConfigs = OAuthAppConfig::query()
        ->where('scope', OAuthAppConfig::SCOPE_USER)
        ->where('user_id', $user->id)
        ->orderBy('provider')
        ->get();

    if ($sourceConfigs->isEmpty()) {
        $this->warn("No user-scoped OAuth configs found for {$user->email}.");

        return 0;
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($sourceConfigs as $config) {
        $existingShared = OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_SHARED)
            ->whereNull('user_id')
            ->where('provider', $config->provider)
            ->first();

        if ($existingShared && ! $overwrite) {
            $skipped++;
            $this->line("Skipped {$config->provider} (shared config already exists)");
            continue;
        }

        $shared = OAuthAppConfig::updateOrCreate(
            [
                'scope' => OAuthAppConfig::SCOPE_SHARED,
                'user_id' => null,
                'provider' => $config->provider,
            ],
            [
                'client_id' => $config->client_id,
                'client_secret' => $config->client_secret,
                'redirect_uri' => $config->redirect_uri,
            ]
        );

        if ($existingShared) {
            $updated++;
            $this->line("Updated shared {$shared->provider}");
        } else {
            $created++;
            $this->line("Created shared {$shared->provider}");
        }
    }

    $this->newLine();
    $this->info("Done. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}");

    return 0;
})->purpose('Copy a user\'s OAuth app configs into shared defaults');
