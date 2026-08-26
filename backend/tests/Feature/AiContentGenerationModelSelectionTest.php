<?php

namespace Tests\Feature;

use App\Models\AiProviderCredential;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Provider/model override parity for content generation (campaign generate,
 * refine, variants) — mirrors AiImageReferenceGenerationTest's structure for
 * the image side.
 *
 * The queue is sync in tests, so each job runs inline and we can assert on
 * the outbound HTTP call the moment the 202 comes back.
 */
class AiContentGenerationModelSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function fakeGroq(): void
    {
        config(['services.ai.groq.api_key' => 'platform-groq-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'A generated caption. #brand']]],
            ]),
        ]);
    }

    private function makeCampaign(): Campaign
    {
        return Campaign::create([
            'user_id' => $this->user->id,
            'name' => 'Launch campaign',
            'status' => 'draft',
            'product_info' => 'A premium productivity app for remote teams.',
            'platforms' => ['instagram'],
        ]);
    }

    private function makePackage(?Campaign $campaign = null): ContentPackage
    {
        $campaign ??= $this->makeCampaign();

        return ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Original caption text.',
            'status' => 'draft',
            'version' => 1,
            'ai_score' => 0.5,
        ]);
    }

    public function test_campaign_generate_uses_an_explicit_provider_and_model(): void
    {
        $this->fakeGroq();

        $campaign = $this->makeCampaign();

        $this->postJson("/api/campaigns/{$campaign->id}/generate", [
            'provider' => 'groq',
            'model' => 'openai/gpt-oss-20b',
        ])->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && ($request->data()['model'] ?? null) === 'openai/gpt-oss-20b';
        });

        $this->assertNotEmpty(ContentPackage::where('campaign_id', $campaign->id)->get());
    }

    public function test_refine_uses_an_explicit_provider_and_model(): void
    {
        $this->fakeGroq();

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/refine", [
            'instruction' => 'Make it punchier.',
            'provider' => 'groq',
            'model' => 'moonshotai/kimi-k2-instruct',
        ])->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && ($request->data()['model'] ?? null) === 'moonshotai/kimi-k2-instruct';
        });

        $refined = ContentPackage::query()->where('parent_id', $package->id)->latest('id')->first();
        $this->assertNotNull($refined);
    }

    public function test_variants_use_an_explicit_provider_and_model(): void
    {
        $this->fakeGroq();

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/variants", [
            'provider' => 'groq',
            'model' => 'llama-3.1-8b-instant',
        ])->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && ($request->data()['model'] ?? null) === 'llama-3.1-8b-instant';
        });

        $package->refresh();
        $group = ContentPackage::where('variant_group_id', $package->variant_group_id)->get();
        $this->assertCount(4, $group);
    }

    public function test_a_users_saved_default_provider_and_model_are_used_when_none_are_supplied(): void
    {
        $this->fakeGroq();

        $this->user->update(['ai_content_settings' => [
            'default_provider' => 'groq',
            'default_model' => 'openai/gpt-oss-20b',
        ]]);

        $campaign = $this->makeCampaign();

        $this->postJson("/api/campaigns/{$campaign->id}/generate")->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && ($request->data()['model'] ?? null) === 'openai/gpt-oss-20b';
        });
    }

    public function test_an_explicit_provider_overrides_the_saved_default(): void
    {
        $this->fakeGroq();

        // Stub is not selectable as a saved default, so simulate an override
        // away from a saved groq default toward an explicit one via model only
        // by first saving a default model that the override should replace.
        $this->user->update(['ai_content_settings' => [
            'default_provider' => 'groq',
            'default_model' => 'openai/gpt-oss-20b',
        ]]);

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/refine", [
            'instruction' => 'Shorten it.',
            'model' => 'llama-3.1-8b-instant',
        ])->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && ($request->data()['model'] ?? null) === 'llama-3.1-8b-instant';
        });
    }

    public function test_a_byok_key_is_used_instead_of_the_platform_key(): void
    {
        $this->fakeGroq();

        AiProviderCredential::create([
            'user_id' => $this->user->id,
            'kind' => 'text',
            'provider' => 'groq',
            'api_key' => 'gsk-user-own-key',
        ]);

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/refine", [
            'instruction' => 'Shorten it.',
            'provider' => 'groq',
        ])->assertAccepted();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gsk-user-own-key'));
    }

    public function test_an_unknown_provider_is_rejected_by_validation(): void
    {
        $campaign = $this->makeCampaign();

        $this->postJson("/api/campaigns/{$campaign->id}/generate", ['provider' => 'chatgpt'])
            ->assertStatus(422);
    }

    public function test_an_unknown_model_is_rejected_by_validation(): void
    {
        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/refine", [
            'instruction' => 'Shorten it.',
            'provider' => 'groq',
            'model' => 'not-a-real-model',
        ])->assertStatus(422);

        $this->postJson("/api/content-packages/{$package->id}/variants", [
            'model' => 'not-a-real-model',
        ])->assertStatus(422);
    }
}
