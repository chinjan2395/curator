<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use Carbon\Carbon;

class AutoPilotService
{
    /**
     * @return array{scheduled: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public function run(Campaign $campaign): array
    {
        $scheduled = [];
        $skipped = [];
        $scheduledAt = $this->nextWeekdayAt9am();

        $platforms = $this->resolvePlatforms($campaign);

        foreach ($platforms as $platform) {
            $package = $this->bestApprovedPackage($campaign, $platform);

            if (! $package) {
                $skipped[] = [
                    'platform' => $platform,
                    'reason' => 'no_approved_package',
                ];

                continue;
            }

            $credential = SocialCredential::query()
                ->where('user_id', $campaign->user_id)
                ->where('provider', $platform)
                ->where('status', '!=', 'disconnected')
                ->orderBy('id')
                ->first();

            if (! $credential) {
                $skipped[] = [
                    'platform' => $platform,
                    'reason' => 'no_credential',
                    'content_package_id' => $package->id,
                ];

                continue;
            }

            $post = ScheduledPost::create([
                'user_id' => $campaign->user_id,
                'social_credential_id' => $credential->id,
                'content_package_id' => $package->id,
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
            ]);

            $package->update(['status' => 'scheduled']);

            $scheduled[] = [
                'platform' => $platform,
                'content_package_id' => $package->id,
                'scheduled_post_id' => $post->id,
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ];
        }

        return [
            'scheduled' => $scheduled,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePlatforms(Campaign $campaign): array
    {
        $fromCampaign = array_values(array_filter(
            is_array($campaign->platforms) ? $campaign->platforms : [],
            fn (mixed $platform) => is_string($platform) && $platform !== '',
        ));

        if ($fromCampaign !== []) {
            return $fromCampaign;
        }

        return ContentPackage::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'approved')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform')
            ->filter(fn (mixed $platform) => is_string($platform) && $platform !== '')
            ->values()
            ->all();
    }

    private function bestApprovedPackage(Campaign $campaign, string $platform): ?ContentPackage
    {
        return ContentPackage::query()
            ->where('campaign_id', $campaign->id)
            ->where('platform', $platform)
            ->where('status', 'approved')
            ->orderByDesc('ai_score')
            ->orderByDesc('id')
            ->first();
    }

    private function nextWeekdayAt9am(): Carbon
    {
        $date = Carbon::now()->addDay()->setTime(9, 0, 0);

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date->utc();
    }
}
