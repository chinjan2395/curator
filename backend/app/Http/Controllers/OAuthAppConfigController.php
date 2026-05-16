<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertOAuthAppConfigRequest;
use App\Models\OAuthAppConfig;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthAppConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $providers = OAuthAppConfig::query()
            ->select('provider')
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider');

        $items = $providers->map(function (string $provider) use ($user) {
            $shared   = $this->findConfig(OAuthAppConfig::SCOPE_SHARED, null, $provider);
            $override = $this->findConfig(OAuthAppConfig::SCOPE_USER, $user->id, $provider);
            $effective = $override ?: $shared;

            return [
                'provider'       => $provider,
                'shared'         => $this->formatConfig($shared),
                'user'           => $this->formatConfig($override),
                'effective'      => $this->formatConfig($effective),
                'effective_scope' => $effective?->scope,
            ];
        })->values();

        return response()->json([
            'is_admin' => $user->isAdmin(),
            'items'    => $items,
        ]);
    }

    public function upsert(UpsertOAuthAppConfigRequest $request): JsonResponse
    {
        /** @var User $user */
        $user      = $request->user();
        $validated = $request->validated();
        $scope     = $validated['scope'];

        if ($scope === OAuthAppConfig::SCOPE_SHARED && ! $user->isAdmin()) {
            return response()->json(['message' => 'Only admins can manage shared OAuth app settings.'], 403);
        }

        $ownerUserId = $scope === OAuthAppConfig::SCOPE_USER ? $user->id : null;
        $existing    = $this->findConfig($scope, $ownerUserId, $validated['provider']);

        $clientSecret = trim((string) ($validated['client_secret'] ?? ''));
        if (($clientSecret === '' || $clientSecret === '__KEEP__') && $existing) {
            $clientSecret = $existing->client_secret;
        }
        if ($clientSecret === '' && ! $existing) {
            return response()->json(['message' => 'Client secret is required.'], 422);
        }

        $config = OAuthAppConfig::updateOrCreate(
            ['scope' => $scope, 'user_id' => $ownerUserId, 'provider' => $validated['provider']],
            [
                'client_id'     => $validated['client_id'],
                'redirect_uri'  => trim((string) ($validated['redirect_uri'] ?? '')) ?: null,
                'client_secret' => $clientSecret,
            ]
        );

        ActivityLogger::log(
            $user,
            'oauth_app.configured',
            "Configured {$scope} OAuth app for {$validated['provider']}",
        );

        return response()->json($this->formatConfig($config), 201);
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        /** @var User $user */
        $user  = $request->user();
        $scope = (string) $request->query('scope', OAuthAppConfig::SCOPE_USER);

        if (! in_array($scope, [OAuthAppConfig::SCOPE_USER, OAuthAppConfig::SCOPE_SHARED], true)) {
            return response()->json(['message' => 'Invalid scope.'], 422);
        }
        if ($scope === OAuthAppConfig::SCOPE_SHARED && ! $user->isAdmin()) {
            return response()->json(['message' => 'Only admins can manage shared OAuth app settings.'], 403);
        }

        OAuthAppConfig::query()
            ->where('scope', $scope)
            ->where('user_id', $scope === OAuthAppConfig::SCOPE_USER ? $user->id : null)
            ->where('provider', $provider)
            ->delete();

        ActivityLogger::log(
            $user,
            'oauth_app.removed',
            "Removed {$scope} OAuth app config for {$provider}",
        );

        return response()->json(null, 204);
    }

    public function promoteMyUserConfigsToShared(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Only admins can promote configs to shared defaults.'], 403);
        }

        $overwrite     = (bool) $request->boolean('overwrite', false);
        $sourceConfigs = OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_USER)
            ->where('user_id', $user->id)
            ->orderBy('provider')
            ->get();

        $created = $updated = $skipped = 0;

        foreach ($sourceConfigs as $config) {
            $existingShared = $this->findConfig(OAuthAppConfig::SCOPE_SHARED, null, $config->provider);

            if ($existingShared && ! $overwrite) {
                $skipped++;
                continue;
            }

            OAuthAppConfig::updateOrCreate(
                ['scope' => OAuthAppConfig::SCOPE_SHARED, 'user_id' => null, 'provider' => $config->provider],
                ['client_id' => $config->client_id, 'client_secret' => $config->client_secret, 'redirect_uri' => $config->redirect_uri]
            );

            $existingShared ? $updated++ : $created++;
        }

        ActivityLogger::log(
            $user,
            'oauth_app.promoted',
            "Promoted {$sourceConfigs->count()} user OAuth app config(s) to shared defaults ({$created} created, {$updated} updated, {$skipped} skipped)",
        );

        return response()->json([
            'message'      => 'Promotion complete.',
            'created'      => $created,
            'updated'      => $updated,
            'skipped'      => $skipped,
            'total_source' => $sourceConfigs->count(),
        ]);
    }

    private function findConfig(string $scope, ?int $userId, string $provider): ?OAuthAppConfig
    {
        return OAuthAppConfig::query()
            ->where('scope', $scope)
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
    }

    private function formatConfig(?OAuthAppConfig $config): ?array
    {
        if (! $config) {
            return null;
        }

        return [
            'id'         => $config->id,
            'scope'      => $config->scope,
            'provider'   => $config->provider,
            'client_id'  => $config->client_id,
            'redirect_uri' => $config->redirect_uri,
            'updated_at' => $config->updated_at,
        ];
    }
}
