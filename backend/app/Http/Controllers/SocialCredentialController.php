<?php

namespace App\Http\Controllers;

use App\Models\SocialCredential;
use Illuminate\Http\Request;

class SocialCredentialController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            SocialCredential::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('provider')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
            'access_token' => ['required', 'string', 'max:2048'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $cred = SocialCredential::create([
            'user_id' => $request->user()->id,
            'provider' => $validated['provider'],
            'access_token' => $validated['access_token'],
            'refresh_token' => $validated['refresh_token'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return response()->json($cred, 201);
    }

    public function show(Request $request, SocialCredential $socialCredential)
    {
        if ($socialCredential->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($socialCredential);
    }

    public function update(Request $request, SocialCredential $socialCredential)
    {
        if ($socialCredential->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'provider' => ['sometimes', 'string', 'max:64'],
            'access_token' => ['sometimes', 'string', 'max:2048'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $socialCredential->update($validated);

        return response()->json($socialCredential);
    }

    public function label(Request $request, SocialCredential $socialCredential)
    {
        if ($socialCredential->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'account_label' => ['required', 'string', 'max:255'],
        ]);

        $socialCredential->update(['account_label' => $validated['account_label']]);

        return response()->json($socialCredential);
    }

    public function destroy(Request $request, SocialCredential $socialCredential)
    {
        if ($socialCredential->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $socialCredential->delete();

        return response()->json(null, 204);
    }
}

