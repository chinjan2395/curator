<?php

namespace App\Jobs;

use App\Events\AiGenerationUpdated;
use App\Models\Campaign;
use App\Services\AI\AiContentService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateCampaignContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public int $campaignId,
        public int $userId,
    ) {}

    public function handle(AiContentService $ai, NotificationService $notifications): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if (! $campaign || (int) $campaign->user_id !== $this->userId) {
            return;
        }

        $campaign->loadMissing(['user', 'brandKit', 'template']);
        $platforms = $campaign->platforms ?? ['instagram', 'twitter'];
        $total = count($platforms);

        event(new AiGenerationUpdated(
            $this->userId,
            'campaign_generate',
            $this->campaignId,
            'started',
            ['total' => $total],
            'Generating campaign content…',
        ));

        try {
            $packages = [];

            foreach ($platforms as $index => $platform) {
                event(new AiGenerationUpdated(
                    $this->userId,
                    'campaign_generate',
                    $this->campaignId,
                    'progress',
                    [
                        'platform' => $platform,
                        'step' => $index + 1,
                        'total' => $total,
                    ],
                    "Generating for {$platform}…",
                ));

                $packages[] = $ai->generateForCampaignPlatform($campaign, $platform);
            }

            $ai->finalizeCampaignGeneration($campaign);

            event(new AiGenerationUpdated(
                $this->userId,
                'campaign_generate',
                $this->campaignId,
                'completed',
                ['packages' => collect($packages)->map->toArray()->all()],
                'Campaign content generated.',
            ));

            $notifications->notify(
                $campaign->user,
                'campaign_generated',
                'Campaign content generated',
                "AI generated content for campaign \"{$campaign->name}\".",
                ['campaign_id' => $campaign->id],
            );
        } catch (Throwable $e) {
            event(new AiGenerationUpdated(
                $this->userId,
                'campaign_generate',
                $this->campaignId,
                'failed',
                null,
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
