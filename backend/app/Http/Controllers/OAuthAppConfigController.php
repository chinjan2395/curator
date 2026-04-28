<?php

namespace App\Http\Controllers;

use App\Models\OAuthAppConfig;
use App\Models\User;
use Illuminate\Http\Request;

class OAuthAppConfigController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $providers = OAuthAppConfig::query()
            ->select('provider')
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider');

        $items = $providers->map(function (string $provider) use ($user) {
            $shared = OAuthAppConfig::query()
                ->where('scope', OAuthAppConfig::SCOPE_SHARED)
                ->where('provider', $provider)
                ->first();

            $override = OAuthAppConfig::query()
                ->where('scope', OAuthAppConfig::SCOPE_USER)
                ->where('user_id', $user->id)
                ->where('provider', $provider)
                ->first();

            $effective = $override ?: $shared;

            return [
                'provider' => $provider,
                'shared' => $this->formatConfig($shared),
                'user' => $this->formatConfig($override),
                'effective' => $this->formatConfig($effective),
                'effective_scope' => $effective?->scope,
            ];
        })->values();

        return response()->json([
            'is_admin' => $user->isAdmin(),
            'items' => $items,
        ]);
    }

    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:'.OAuthAppConfig::SCOPE_USER.','.OAuthAppConfig::SCOPE_SHARED],
            'provider' => ['required', 'string', 'max:64'],
            'client_id' => ['required', 'string', 'max:512'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'redirect_uri' => ['nullable', 'string', 'max:1024'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $scope = $validated['scope'];
        if ($scope === OAuthAppConfig::SCOPE_SHARED && ! $user->isAdmin()) {
            return response()->json(['message' => 'Only admins can manage shared OAuth app settings.'], 403);
        }

        $ownerUserId = $scope === OAuthAppConfig::SCOPE_USER ? $user->id : null;
        $existing = OAuthAppConfig::query()
            ->where('scope', $scope)
            ->where('user_id', $ownerUserId)
            ->where('provider', $validated['provider'])
            ->first();

        $clientSecret = trim((string) ($validated['client_secret'] ?? ''));
        if (($clientSecret === '' || $clientSecret === '__KEEP__') && $existing) {
            $clientSecret = $existing->client_secret;
        }
        if ($clientSecret === '' && ! $existing) {
            return response()->json([
                'message' => 'Client secret is required.',
            ], 422);
        }

        $config = OAuthAppConfig::updateOrCreate(
            [
                'scope' => $scope,
                'user_id' => $ownerUserId,
                'provider' => $validated['provider'],
            ],
            [
                'client_id' => $validated['client_id'],
                'redirect_uri' => trim((string) ($validated['redirect_uri'] ?? '')) ?: null,
                // uses mutator to encrypt
                'client_secret' => $clientSecret,
            ]
        );

        return response()->json($this->formatConfig($config), 201);
    }

    public function destroy(Request $request, string $provider)
    {
        /** @var User $user */
        $user = $request->user();
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

        return response()->json(null, 204);
    }

    public function promoteMyUserConfigsToShared(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Only admins can promote configs to shared defaults.'], 403);
        }

        $overwrite = (bool) $request->boolean('overwrite', false);
        $sourceConfigs = OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_USER)
            ->where('user_id', $user->id)
            ->orderBy('provider')
            ->get();

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
                continue;
            }

            OAuthAppConfig::updateOrCreate(
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
            } else {
                $created++;
            }
        }

        return response()->json([
            'message' => 'Promotion complete.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_source' => $sourceConfigs->count(),
        ]);
    }

    private function formatConfig(?OAuthAppConfig $config): ?array
    {
        if (! $config) {
            return null;
        }

        return [
            'id' => $config->id,
            'scope' => $config->scope,
            'provider' => $config->provider,
            'client_id' => $config->client_id,
            'redirect_uri' => $config->redirect_uri,
            'updated_at' => $config->updated_at,
        ];
    }
}

