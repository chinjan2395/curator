<?php

namespace App\Services\AI;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\LearningSignal;
use App\Models\User;
use Illuminate\Support\Str;

class AiContentService
{
    /**
     * The three tone profiles used for A/B variant generation.
     * variant_index => [label, style instruction]
     */
    private const VARIANT_STYLES = [
        1 => ['Direct & Punchy', 'Write a direct, punchy caption. Short sentences. Hook in the first line. No fluff.'],
        2 => ['Conversational', 'Write a warm, conversational caption. Friendly tone, relatable language, end with a question to encourage comments.'],
        3 => ['Story-driven', 'Write a story-driven caption. Open with a scenario or problem, build briefly, close with a clear call-to-action.'],
    ];

    public function __construct(
        private readonly AiProviderInterface $provider,
    ) {}

    public function generateForCampaign(Campaign $campaign): array
    {
        $campaign->loadMissing(['user', 'brandKit', 'template']);
        $packages = [];
        $platforms = $campaign->platforms ?? ['instagram', 'twitter'];
        $context = $this->buildContext($campaign->user, $campaign);

        foreach ($platforms as $platform) {
            $basePrompt = $this->buildGenerationPrompt($campaign, $platform);
            $caption = $this->provider->generateText(
                $basePrompt,
                array_merge($context, ['platform' => $platform]),
            );

            $package = ContentPackage::create([
                'campaign_id' => $campaign->id,
                'user_id' => $campaign->user_id,
                'platform' => $platform,
                'content_type' => $campaign->template?->content_type ?? 'post',
                'caption' => $caption,
                'hashtags' => $this->suggestHashtags($campaign, $platform, $context),
                'status' => 'draft',
                'ai_score' => 0.75,
            ]);

            $packages[] = $package;
        }

        $campaign->update(['status' => 'generated', 'ai_strategy' => ['provider' => $this->provider->name()]]);

        LearningSignal::create([
            'user_id' => $campaign->user_id,
            'action' => 'generated',
            'platform' => null,
            'content_type' => 'campaign',
            'metadata' => ['campaign_id' => $campaign->id],
        ]);

        return $packages;
    }

    /**
     * Generate A/B variants for an existing content package.
     *
     * Creates up to 3 new sibling packages with the same variant_group_id.
     * The original package itself is stamped with the group ID and index 0.
     * Returns all packages in the group (original + variants), ordered by index.
     *
     * @return list<ContentPackage>
     */
    public function generateVariants(ContentPackage $original, int $count = 3): array
    {
        $count = min($count, count(self::VARIANT_STYLES));

        $original->loadMissing('campaign.user');
        $context = $this->buildContext(
            $original->campaign?->user,
            $original->campaign,
            ['platform' => $original->platform],
        );

        $groupId = Str::uuid()->toString();

        // Stamp the original with index 0 in the new group
        $original->update([
            'variant_group_id' => $groupId,
            'variant_index' => 0,
        ]);

        $basePrompt = "Original caption:\n{$original->caption}\n\nProduct/brief: ".($original->campaign?->product_info ?? '');

        $variants = [$original->fresh()];

        foreach (array_slice(self::VARIANT_STYLES, 0, $count, true) as $index => [$label, $styleInstruction]) {
            $prompt = "{$styleInstruction}\n\n{$basePrompt}";

            try {
                $caption = $this->provider->generateText($prompt, $context);
            } catch (\RuntimeException) {
                // Fall back to a stub variant so the group is still usable
                $caption = "[{$label}] " . $original->caption;
            }

            $variant = ContentPackage::create([
                'campaign_id' => $original->campaign_id,
                'user_id' => $original->user_id,
                'platform' => $original->platform,
                'content_type' => $original->content_type,
                'caption' => $caption,
                'hashtags' => $original->hashtags,
                'media_urls' => $original->media_urls,
                'status' => 'draft',
                'ai_score' => $original->ai_score,
                'variant_group_id' => $groupId,
                'variant_index' => $index,
            ]);

            $variants[] = $variant;
        }

        LearningSignal::create([
            'user_id' => $original->user_id,
            'action' => 'ab_variants_generated',
            'platform' => $original->platform,
            'content_type' => 'content_package',
            'metadata' => [
                'original_id' => $original->id,
                'variant_group_id' => $groupId,
                'variant_count' => $count,
            ],
        ]);

        return $variants;
    }

    /**
     * Mark one package as the winner of its variant group.
     * All other packages in the group are rejected.
     */
    public function markVariantWinner(ContentPackage $winner): ContentPackage
    {
        if (! $winner->variant_group_id) {
            $winner->update(['is_winner' => true]);

            return $winner->fresh();
        }

        ContentPackage::query()
            ->where('variant_group_id', $winner->variant_group_id)
            ->where('id', '!=', $winner->id)
            ->update(['is_winner' => false, 'status' => 'rejected']);

        $winner->update(['is_winner' => true, 'status' => 'approved']);

        LearningSignal::create([
            'user_id' => $winner->user_id,
            'action' => 'ab_winner_selected',
            'platform' => $winner->platform,
            'content_type' => 'content_package',
            'metadata' => [
                'winner_id' => $winner->id,
                'variant_index' => $winner->variant_index,
                'variant_group_id' => $winner->variant_group_id,
            ],
        ]);

        return $winner->fresh();
    }

    public function refine(ContentPackage $package, string $instruction): ContentPackage
    {
        $package->loadMissing('campaign.user');
        $context = $this->buildContext(
            $package->campaign?->user,
            $package->campaign,
            ['platform' => $package->platform],
        );

        $caption = $this->provider->generateText(
            "Refine this caption with instruction: {$instruction}. Original: {$package->caption}",
            $context,
        );

        return ContentPackage::create([
            'campaign_id' => $package->campaign_id,
            'user_id' => $package->user_id,
            'platform' => $package->platform,
            'content_type' => $package->content_type,
            'caption' => $caption,
            'hashtags' => $package->hashtags,
            'status' => 'draft',
            'version' => $package->version + 1,
            'parent_id' => $package->id,
            'ai_score' => $package->ai_score,
        ]);
    }

    private function buildGenerationPrompt(Campaign $campaign, string $platform): string
    {
        $tone = $campaign->tone ? "{$campaign->tone} " : '';
        $base = "Create a {$tone}caption for {$platform}: {$campaign->product_info}";

        if ($campaign->template?->template_data) {
            $starter = $campaign->template->template_data['caption'] ?? '';
            if ($starter !== '') {
                $base .= "\n\nUse this as a starting structure:\n{$starter}";
            }
        }

        return $base;
    }

    /** @return array<string, mixed> */
    private function buildContext(?User $user, ?Campaign $campaign = null, array $extra = []): array
    {
        $context = [
            'brand_voice' => $user?->brand_voice,
            'target_audience' => $user?->target_audience ?? $campaign?->target_audience,
            'tone' => $campaign?->tone,
            'goals' => $campaign?->goals,
            'campaign_name' => $campaign?->name,
            'prompt_overrides' => $user?->ai_prompt_overrides,
        ];

        // Inject brand kit colors and fonts so providers can reference them in the system prompt
        if ($campaign?->brandKit) {
            $kit = $campaign->brandKit;
            if (!empty($kit->colors['primary'])) {
                $context['brand_primary_color'] = $kit->colors['primary'];
            }
            if (!empty($kit->fonts['heading']) && $kit->fonts['heading'] !== 'inherit') {
                $context['brand_font'] = $kit->fonts['heading'];
            }
            $context['brand_kit_name'] = $kit->name;
        }

        return array_filter(array_merge($context, $extra), static fn ($v) => $v !== null && $v !== '');
    }

    /** @return list<string> */
    private function suggestHashtags(Campaign $campaign, string $platform, array $context): array
    {
        if ($this->provider->name() === 'stub') {
            $base = array_filter(preg_split('/\s+/', strtolower((string) $campaign->name)) ?: []);

            return array_slice(array_map(static fn ($w) => '#'.$w, $base), 0, 5);
        }

        try {
            $text = $this->provider->generateText(
                "Suggest 5 hashtags for {$platform} about: {$campaign->product_info}. Return only hashtags separated by spaces.",
                array_merge($context, ['platform' => $platform]),
            );
            preg_match_all('/#\w+/u', $text, $matches);

            return array_slice($matches[0] ?? [], 0, 8);
        } catch (\Throwable) {
            return [];
        }
    }
}
