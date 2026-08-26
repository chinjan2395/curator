<?php

namespace App\Jobs;

use App\Events\AiGenerationUpdated;
use App\Models\ContentPackage;
use App\Services\AI\AiImageGenerationService;
use App\Services\AI\Image\ImageGenerationOptions;
use App\Support\AiImageProviders;
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

    /** FLUX polls for a finished image, so allow well beyond a single request. */
    public int $timeout = 600;

    /** Set once handle() has already told the client the generation failed. */
    private bool $failureBroadcast = false;

    public function __construct(
        public int $contentPackageId,
        public int $userId,
        public ?string $instruction = null,
        public ?int $referenceAssetId = null,
        public ?string $provider = null,
        public ?string $prompt = null,
        public ?string $model = null,
    ) {
        $this->onQueue('high');
    }

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
            $this->startedMessage(),
        ));

        try {
            $updated = $imageGeneration->generateForPackage($package, new ImageGenerationOptions(
                instruction: $this->instruction,
                provider: $this->provider,
                referenceAssetId: $this->referenceAssetId,
                prompt: $this->prompt,
                model: $this->model,
            ));

            event(new AiGenerationUpdated(
                $this->userId,
                'image',
                $this->contentPackageId,
                'completed',
                ['package' => $updated->toArray()],
                'Image generated and attached.',
            ));
        } catch (Throwable $e) {
            $this->failureBroadcast = true;

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

    /**
     * A crash outside handle()'s try/catch (timeout, out of memory, retries
     * exhausted) would otherwise leave the UI spinning forever.
     */
    public function failed(?Throwable $e): void
    {
        if ($this->failureBroadcast) {
            return;
        }

        event(new AiGenerationUpdated(
            $this->userId,
            'image',
            $this->contentPackageId,
            'failed',
            null,
            $e?->getMessage() ?: 'Image generation failed.',
        ));
    }

    private function startedMessage(): string
    {
        if ($this->provider !== null && AiImageProviders::exists($this->provider)) {
            return 'Generating image with '.AiImageProviders::label($this->provider).'…';
        }

        return 'Generating image…';
    }
}
