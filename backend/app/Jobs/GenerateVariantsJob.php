<?php

namespace App\Jobs;

use App\Events\AiGenerationUpdated;
use App\Models\ContentPackage;
use App\Services\AI\AiContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $contentPackageId,
        public int $userId,
        public int $count = 3,
    ) {}

    public function handle(AiContentService $ai): void
    {
        $package = ContentPackage::query()->find($this->contentPackageId);
        if (! $package || (int) $package->user_id !== $this->userId) {
            return;
        }

        event(new AiGenerationUpdated(
            $this->userId,
            'variants',
            $this->contentPackageId,
            'started',
            null,
            'Generating variants…',
        ));

        try {
            $variants = $ai->generateVariants($package, $this->count);

            event(new AiGenerationUpdated(
                $this->userId,
                'variants',
                $this->contentPackageId,
                'completed',
                ['variants' => collect($variants)->map->toArray()->all()],
                'Variants generated.',
            ));
        } catch (Throwable $e) {
            event(new AiGenerationUpdated(
                $this->userId,
                'variants',
                $this->contentPackageId,
                'failed',
                null,
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
