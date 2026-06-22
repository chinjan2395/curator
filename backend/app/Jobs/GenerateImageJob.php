<?php

namespace App\Jobs;

use App\Events\AiGenerationUpdated;
use App\Models\ContentPackage;
use App\Services\AI\AiImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public int $contentPackageId,
        public int $userId,
        public ?string $instruction = null,
    ) {}

    public function handle(AiImageGenerationService $imageGeneration): void
    {
        $package = ContentPackage::query()->find($this->contentPackageId);
        if (! $package || (int) $package->user_id !== $this->userId) {
            return;
        }

        event(new AiGenerationUpdated(
            $this->userId,
            'image',
            $this->contentPackageId,
            'started',
            null,
            'Generating image…',
        ));

        try {
            $updated = $imageGeneration->generateForPackage($package, $this->instruction);

            event(new AiGenerationUpdated(
                $this->userId,
                'image',
                $this->contentPackageId,
                'completed',
                ['package' => $updated->toArray()],
                'Image generated and attached.',
            ));
        } catch (Throwable $e) {
            event(new AiGenerationUpdated(
                $this->userId,
                'image',
                $this->contentPackageId,
                'failed',
                null,
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
