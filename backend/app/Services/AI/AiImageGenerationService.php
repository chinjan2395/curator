<?php

namespace App\Services\AI;

use App\Models\Asset;
use App\Models\ContentPackage;
use App\Models\LearningSignal;
use App\Services\Content\AssetTaggingService;
use App\Support\ContentPackageMediaResolver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiImageGenerationService
{
    public function __construct(
        private readonly AiImageProviderInterface $provider,
        private readonly AssetTaggingService $tagging,
        private readonly ContentPackageMediaResolver $mediaResolver,
    ) {}

    public function generateForPackage(ContentPackage $package, ?string $instruction = null): ContentPackage
    {
        $package->loadMissing(['campaign.brandKit', 'campaign.user']);

        $context = $this->buildImageContext($package);
        $prompt = $this->buildImagePrompt($package, $instruction);
        $generated = $this->provider->generateImage($prompt, $context);

        $extension = $this->extensionForMime($generated['mime_type']);
        $path = 'assets/'.$package->user_id.'/generated/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk('public')->put($path, $generated['content']);

        $fileName = 'ai-'.$package->platform.'-'.now()->format('Ymd-His').'.'.$extension;

        $asset = Asset::create([
            'user_id' => $package->user_id,
            'campaign_id' => $package->campaign_id,
            'type' => 'image',
            'file_name' => $fileName,
            'file_size' => strlen($generated['content']),
            'mime_type' => $generated['mime_type'],
            'storage_path' => $path,
            'storage_disk' => 'public',
            'ai_tags' => ['ai-generated', $package->platform],
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
                'provider' => $this->provider->name(),
            ],
        ]);

        return $package->fresh();
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

            $colors = is_array($kit->colors) ? $kit->colors : [];
            foreach (['primary', 'secondary', 'accent', 'background', 'text'] as $colorKey) {
                if (! empty($colors[$colorKey])) {
                    $context['brand_color_'.$colorKey] = $colors[$colorKey];
                }
            }
        }

        return array_filter($context, static fn ($value) => $value !== null && $value !== '');
    }

    private function buildImagePrompt(ContentPackage $package, ?string $instruction): string
    {
        $parts = [];

        if (is_string($instruction) && trim($instruction) !== '') {
            $parts[] = trim($instruction);
        }

        $parts[] = 'Create a social media image for '.$package->platform;

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
            $colors = is_array($kit->colors) ? $kit->colors : [];
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

        $parts[] = 'No text overlays unless essential. Professional, scroll-stopping composition.';

        return implode('. ', $parts);
    }

    private function extensionForMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
