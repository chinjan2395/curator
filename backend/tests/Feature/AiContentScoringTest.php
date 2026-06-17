<?php

namespace Tests\Feature;

use App\Models\BrandKit;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiContentScoringTest extends TestCase
{
    use RefreshDatabase;

    private function makeCampaign(User $user, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Launch campaign',
            'status' => 'draft',
            'product_info' => 'A premium productivity app for remote teams.',
            'platforms' => ['instagram'],
        ], $overrides));
    }

    private function makePackage(User $user, Campaign $campaign, array $overrides = []): ContentPackage
    {
        return ContentPackage::create(array_merge([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Short caption for testing.',
            'status' => 'draft',
            'version' => 1,
            'ai_score' => 0.42,
        ], $overrides));
    }

    public function test_generate_sets_real_ai_score_with_stub(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = $this->makeCampaign($user);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/generate");

        $response->assertOk();
        $packages = $response->json('data');
        $this->assertCount(1, $packages);
        $this->assertNotNull($packages[0]['ai_score']);
        $this->assertNotSame(0.75, $packages[0]['ai_score']);
        $this->assertGreaterThan(0, $packages[0]['ai_score']);
        $this->assertLessThanOrEqual(1, $packages[0]['ai_score']);
    }

    public function test_refine_updates_ai_score(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = $this->makeCampaign($user);
        $package = $this->makePackage($user, $campaign, [
            'caption' => str_repeat('x', 40),
            'ai_score' => 0.2,
        ]);

        $response = $this->postJson("/api/content-packages/{$package->id}/refine", [
            'instruction' => 'Make it shorter and punchier.',
        ]);

        $response->assertOk();
        $refined = $response->json('data');
        $this->assertNotNull($refined['ai_score']);
        $this->assertNotSame($package->ai_score, $refined['ai_score']);
    }

    public function test_variants_get_individual_scores(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = $this->makeCampaign($user);
        $package = $this->makePackage($user, $campaign, [
            'caption' => 'Base caption for variant generation.',
            'ai_score' => 0.33,
        ]);

        $response = $this->postJson("/api/content-packages/{$package->id}/variants");

        $response->assertOk();
        $group = $response->json('data');
        $this->assertCount(4, $group);

        $variantScores = collect($group)
            ->where('variant_index', '>', 0)
            ->pluck('ai_score')
            ->all();

        $this->assertCount(3, $variantScores);
        foreach ($variantScores as $score) {
            $this->assertNotNull($score);
            $this->assertGreaterThan(0, $score);
            $this->assertLessThanOrEqual(1, $score);
        }

        $this->assertFalse(
            collect($variantScores)->every(static fn ($score) => (float) $score === (float) $package->ai_score),
            'Variants should be scored independently from the parent package.',
        );
    }

    public function test_build_context_includes_full_brand_kit_fields(): void
    {
        $user = User::factory()->create();
        $kit = BrandKit::create([
            'user_id' => $user->id,
            'name' => 'Acme Brand',
            'colors' => [
                'primary' => '#111111',
                'secondary' => '#222222',
                'accent' => '#333333',
                'background' => '#ffffff',
                'text' => '#000000',
            ],
            'fonts' => [
                'heading' => 'Georgia',
                'body' => 'Inter',
            ],
        ]);

        $campaign = $this->makeCampaign($user, [
            'brand_kit_id' => $kit->id,
            'description' => 'Summer launch narrative.',
        ]);

        $service = app(\App\Services\AI\AiContentService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildContext');
        $method->setAccessible(true);

        $campaign->load('brandKit');
        $context = $method->invoke($service, $user, $campaign, ['platform' => 'instagram']);

        $this->assertSame('Acme Brand', $context['brand_kit_name']);
        $this->assertSame('#111111', $context['brand_color_primary']);
        $this->assertSame('#222222', $context['brand_color_secondary']);
        $this->assertSame('#333333', $context['brand_color_accent']);
        $this->assertSame('Georgia', $context['brand_font_heading']);
        $this->assertSame('Inter', $context['brand_font_body']);
        $this->assertSame('Summer launch narrative.', $context['description']);
    }
}
