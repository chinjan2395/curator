<?php

namespace App\Services;

use App\Models\LearningSignal;
use App\Models\User;

class LearningPromptService
{
    public function recordAndRefresh(User $user, string $action, ?string $platform = null, ?array $metadata = null): void
    {
        LearningSignal::create([
            'user_id' => $user->id,
            'action' => $action,
            'platform' => $platform,
            'content_type' => $metadata['content_type'] ?? null,
            'metadata' => $metadata,
        ]);

        $this->refreshOverrides($user);
    }

    public function refreshOverrides(User $user): void
    {
        $signals = LearningSignal::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $byPlatform = [];
        foreach ($signals as $signal) {
            $platform = $signal->platform ?? 'general';
            $byPlatform[$platform][] = $signal->action;
        }

        $overrides = [];
        foreach ($byPlatform as $platform => $actions) {
            $counts = array_count_values($actions);
            arsort($counts);
            $top = array_key_first($counts);
            $overrides[$platform] = "User frequently {$top} content on {$platform}.";
        }

        $user->update(['ai_prompt_overrides' => implode(' ', array_values($overrides))]);
    }
}
