<?php

namespace App\Http\Controllers;

use App\Models\OAuthAppConfig;
use Illuminate\Http\Request;

class OAuthAppConfigController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            OAuthAppConfig::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('provider')
                ->get()
                ->map(fn (OAuthAppConfig $c) => [
                    'id' => $c->id,
                    'provider' => $c->provider,
                    'client_id' => $c->client_id,
                    'redirect_uri' => $c->redirect_uri,
                    'updated_at' => $c->updated_at,
                ])
        );
    }

    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
            'client_id' => ['required', 'string', 'max:512'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'redirect_uri' => ['nullable', 'string', 'max:1024'],
        ]);

        $existing = OAuthAppConfig::query()
            ->where('user_id', $request->user()->id)
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
                'user_id' => $request->user()->id,
                'provider' => $validated['provider'],
            ],
            [
                'client_id' => $validated['client_id'],
                'redirect_uri' => trim((string) ($validated['redirect_uri'] ?? '')) ?: null,
                // uses mutator to encrypt
                'client_secret' => $clientSecret,
            ]
        );

        return response()->json([
            'id' => $config->id,
            'provider' => $config->provider,
            'client_id' => $config->client_id,
            'redirect_uri' => $config->redirect_uri,
            'updated_at' => $config->updated_at,
        ], 201);
    }

    public function destroy(Request $request, string $provider)
    {
        OAuthAppConfig::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->delete();

        return response()->json(null, 204);
    }
}

