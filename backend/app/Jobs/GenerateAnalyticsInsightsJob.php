<?php

namespace App\Jobs;

use App\Events\AnalyticsInsightsUpdated;
use App\Models\User;
use App\Services\AI\AiInsightsService;
use App\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateAnalyticsInsightsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $userId) {}

    public function handle(AnalyticsService $analytics, AiInsightsService $aiInsights): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        event(new AnalyticsInsightsUpdated($this->userId, 'started', null, 'Generating AI insights…'));

        try {
            $overview = $analytics->overview($user);
            $heuristic = $analytics->insights($user);
            $llm = $aiInsights->generateInsights($overview);
            $insights = array_values(array_unique(array_merge($llm, $heuristic)));

            event(new AnalyticsInsightsUpdated(
                $this->userId,
                'completed',
                $insights,
                'AI insights ready.',
            ));
        } catch (Throwable $e) {
            event(new AnalyticsInsightsUpdated(
                $this->userId,
                'failed',
                null,
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
