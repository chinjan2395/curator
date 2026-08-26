<?php

namespace App\Services\AI;

use App\Models\Asset;
use App\Models\ContentPackage;
use App\Models\LearningSignal;
use App\Models\User;
use App\Services\AI\Image\AiImageProviderFactory;
use App\Services\AI\Image\ImageGenerationOptions;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Services\AI\Image\ImageKeyResolver;
use App\Services\AI\Image\ReferenceImage;
use App\Services\Content\AssetStorageService;
use App\Services\Content\AssetTaggingService;
use App\Support\AiImageProviders;
use App\Support\AssetBinary;
use App\Support\ContentPackageMediaResolver;
use Illuminate\Support\Str;
use RuntimeException;

class AiImageGenerationService
{
    public function __construct(
        private readonly AiImageProviderFactory $providers,
        private readonly ImageKeyResolver $keys,
        private readonly AssetTaggingService $tagging,
        private readonly ContentPackageMediaResolver $mediaResolver,
        private readonly AssetStorageService $assetStorage,
    ) {}

    public function generateForPackage(ContentPackage $package, ?ImageGenerationOptions $options = null): ContentPackage
    {
        $options ??= new ImageGenerationOptions;

        $package->loadMissing(['campaign.brandKit', 'campaign.user', 'user']);

        $user = $package->user ?? $package->campaign?->user;
        $providerId = $this->resolveProviderId($options->provider, $user);
        $key = $this->keys->resolve($providerId, $user);
        $provider = $this->providers->make($key);

        $reference = $this->loadReference($options->referenceAssetId, $package);
        if ($reference !== null && ! $provider->supportsReference()) {
            throw new RuntimeException(
                AiImageProviders::label($providerId).' cannot generate from a reference image.',
            );
        }

        $prompt = is_string($options->prompt) && trim($options->prompt) !== ''
            ? $options->prompt
            : $this->buildImagePrompt($package, $options->instruction, $reference !== null);

        $generated = $provider->generateImage(new ImageGenerationRequest(
            prompt: $prompt,
            context: $this->buildImageContext($package),
            references: $reference !== null ? [$reference] : [],
            size: $this->resolveSize($options->size, $providerId, $user),
            model: $this->resolveModel($options->model, $providerId, $user),
        ));

        $extension = $generated->extension();
        $path = 'assets/'.$package->user_id.'/generated/'.Str::uuid()->toString().'.'.$extension;
        $stored = $this->assetStorage->storeBinary($generated->content, $path);

        $fileName = 'ai-'.$package->platform.'-'.now()->format('Ymd-His').'.'.$extension;

        $asset = Asset::create([
            'user_id' => $package->user_id,
            'campaign_id' => $package->campaign_id,
            'type' => 'image',
            'file_name' => $fileName,
            'file_size' => $generated->bytes(),
            'mime_type' => $generated->mimeType,
            'storage_path' => $stored['path'],
            'storage_disk' => $stored['disk'],
            'ai_tags' => ['ai-generated', $package->platform],
            'prompt' => $prompt,
        ]);

        try {
            $asset->update(['ai_tags' => $this->tagging->suggestTags($asset)]);
        } catch (\Throwable) {
            // Keep default tags when tagging fails.
        }

        $mediaUrls = $this->mediaResolver->merge(
            $package->media_urls,
            null,
            [$asset->id],
            (int) $package->user_id,
        );

        $package->update(['media_urls' => $mediaUrls]);

        LearningSignal::create([
            'user_id' => $package->user_id,
            'action' => 'image_generated',
            'platform' => $package->platform,
            'content_type' => 'content_package',
            'metadata' => [
                'content_package_id' => $package->id,
                'asset_id' => $asset->id,
                'provider' => $provider->name(),
                'key_source' => $key->source,
                'reference_asset_id' => $options->referenceAssetId,
            ],
        ]);

        return $package->fresh();
    }

    /**
     * Explicit override wins, then the user's saved default, then the platform default.
     */
    public function resolveProviderId(?string $requested, ?User $user): string
    {
        foreach ([$requested, $this->userDefault($user, 'default_provider'), config('services.ai.image.driver')] as $candidate) {
            if (is_string($candidate) && AiImageProviders::exists($candidate)) {
                return strtolower(trim($candidate));
            }
        }

        return AiImageProviders::STUB;
    }

    /**
     * Build the composed prompt for a package without generating anything, so the
     * frontend can preview (and optionally edit) it before spending a provider call.
     */
    public function previewPrompt(ContentPackage $package, ?string $instruction, ?int $referenceAssetId): string
    {
        $package->loadMissing(['campaign.brandKit', 'campaign.user', 'user']);

        return $this->buildImagePrompt($package, $instruction, $this->referenceExists($referenceAssetId, $package));
    }

    /**
     * Cheap existence + ownership check for the preview path, which never needs
     * the reference's binary content.
     */
    private function referenceExists(?int $assetId, ContentPackage $package): bool
    {
        if ($assetId === null) {
            return false;
        }

        $asset = Asset::query()->find($assetId);

        return $asset !== null && (int) $asset->user_id === (int) $package->user_id;
    }

    private function resolveSize(?string $requested, string $providerId, ?User $user): ?string
    {
        $sizes = AiImageProviders::sizes($providerId);

        foreach ([$requested, $this->userDefault($user, 'default_size')] as $candidate) {
            // Ignore a saved size the chosen provider does not offer.
            if (is_string($candidate) && in_array($candidate, $sizes, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveModel(?string $requested, string $providerId, ?User $user): ?string
    {
        $models = AiImageProviders::modelIds($providerId);

        foreach ([$requested, $this->userDefault($user, 'default_model')] as $candidate) {
            // Ignore a saved model the chosen provider does not offer.
            if (is_string($candidate) && in_array($candidate, $models, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function userDefault(?User $user, string $key): ?string
    {
        $settings = $user?->ai_image_settings;

        return is_array($settings) && is_string($settings[$key] ?? null) ? $settings[$key] : null;
    }

    /**
     * Load the reference image the user picked, verifying it belongs to them.
     */
    private function loadReference(?int $assetId, ContentPackage $package): ?ReferenceImage
    {
        if ($assetId === null) {
            return null;
        }

        $asset = Asset::query()->find($assetId);
        if (! $asset || (int) $asset->user_id !== (int) $package->user_id) {
            throw new RuntimeException('Reference image was not found.');
        }

        $binary = AssetBinary::read($asset);
        if ($binary === null) {
            throw new RuntimeException('Reference image could not be read from storage.');
        }

        if (! ReferenceImage::isAllowedMimeType($binary['mime'])) {
            throw new RuntimeException('Reference image must be a PNG, JPEG, or WebP file.');
        }

        if (strlen($binary['content']) > ReferenceImage::MAX_BYTES) {
            throw new RuntimeException('Reference image is larger than 8 MB.');
        }

        return new ReferenceImage(
            content: $binary['content'],
            mimeType: strtolower($binary['mime']),
            fileName: $asset->file_name ?: 'reference.png',
        );
    }

    /** @return array<string, mixed> */
    private function buildImageContext(ContentPackage $package): array
    {
        $campaign = $package->campaign;
        $user = $campaign?->user;

        $context = [
            'platform' => $package->platform,
            'caption' => $package->caption,
            'campaign_name' => $campaign?->name,
            'product_info' => $campaign?->product_info,
            'description' => $campaign?->description,
            'tone' => $campaign?->tone,
            'brand_voice' => $user?->brand_voice,
        ];

        if ($campaign?->brandKit) {
            $kit = $campaign->brandKit;
            $context['brand_kit_name'] = $kit->name;

            $resolved = $kit->resolve();
            $colors = is_array($resolved['colors'] ?? null) ? $resolved['colors'] : [];
            foreach (['primary', 'secondary', 'accent', 'background', 'text'] as $colorKey) {
                if (! empty($colors[$colorKey])) {
                    $context['brand_color_'.$colorKey] = $colors[$colorKey];
                }
            }
        }

        return array_filter($context, static fn ($value) => $value !== null && $value !== '');
    }

    private function buildImagePrompt(ContentPackage $package, ?string $instruction, bool $hasReference): string
    {
        $parts = [];

        if (is_string($instruction) && trim($instruction) !== '') {
            $parts[] = trim($instruction);
        }

        // With a reference the provider is editing a supplied image, so say that
        // rather than asking for a picture from nothing.
        $parts[] = $hasReference
            ? 'Use the supplied reference image as the basis for a social media image for '.$package->platform
            : 'Create a social media image for '.$package->platform;

        if ($package->caption) {
            $parts[] = 'Visual concept inspired by this caption: '.$package->caption;
        }

        if ($package->campaign?->product_info) {
            $parts[] = 'Product or service: '.$package->campaign->product_info;
        }

        if ($package->campaign?->description) {
            $parts[] = 'Campaign context: '.$package->campaign->description;
        }

        if ($package->campaign?->tone) {
            $parts[] = 'Tone: '.$package->campaign->tone;
        }

        $kit = $package->campaign?->brandKit;
        if ($kit) {
            $colorBits = [];
            $kitColors = $kit->resolve()['colors'] ?? [];
            $colors = is_array($kitColors) ? $kitColors : [];
            foreach (['primary', 'secondary', 'accent'] as $key) {
                if (! empty($colors[$key])) {
                    $colorBits[] = $key.' '.$colors[$key];
                }
            }
            if ($colorBits !== []) {
                $parts[] = 'Use brand colors: '.implode(', ', $colorBits);
            }
            if (! empty($kit->name)) {
                $parts[] = 'Brand: '.$kit->name;
            }
        }

        if ($hasReference) {
            $parts[] = 'Preserve the subject and composition of the reference image unless the instruction says otherwise.';
        }

        $parts[] = 'No text overlays unless essential. Professional, scroll-stopping composition.';

        return implode('. ', $parts);
    }
}
