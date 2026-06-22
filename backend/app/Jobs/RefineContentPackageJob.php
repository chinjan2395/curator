<?php

namespace App\Jobs;

use App\Events\AiGenerationUpdated;
use App\Models\ContentPackage;
use App\Services\AI\AiContentService;
use App\Services\LearningPromptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefineContentPackageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public int $contentPackageId,
        public int $userId,
        public string $instruction,
    ) {}

    public function handle(AiContentService $ai, LearningPromptService $learning): void
    {
        $package = ContentPackage::query()->find($this->contentPackageId);
        if (! $package || (int) $package->user_id !== $this->userId) {
            return;
        }

        event(new AiGenerationUpdated(
            $this->userId,
            'refine',
            $this->contentPackageId,
            'started',
            null,
            'Refining caption…',
        ));

        try {
            $refined = $ai->refine($package, $this->instruction);

            $learning->recordAndRefresh($package->user, 'refined', $package->platform, [
                'content_package_id' => $package->id,
                'instruction' => $this->instruction,
            ]);

            event(new AiGenerationUpdated(
                $this->userId,
                'refine',
                $this->contentPackageId,
                'completed',
                ['package' => $refined->toArray()],
                'Caption refined.',
            ));
        } catch (Throwable $e) {
            event(new AiGenerationUpdated(
                $this->userId,
                'refine',
                $this->contentPackageId,
                'failed',
                null,
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
