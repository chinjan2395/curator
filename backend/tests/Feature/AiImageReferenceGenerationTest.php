<?php

namespace Tests\Feature;

use App\Models\AiProviderCredential;
use App\Models\Asset;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\LearningSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The reference-image and multi-provider half of generate-image.
 *
 * The queue is sync in tests, so the job runs inline and we can assert on the
 * asset and outbound HTTP call the moment the 202 comes back.
 */
class AiImageReferenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function makePackage(?User $user = null): ContentPackage
    {
        $user ??= $this->user;

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Visual campaign',
            'status' => 'generated',
            'product_info' => 'Organic skincare line',
            'platforms' => ['instagram'],
        ]);

        return ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Glow naturally with our new serum.',
            'status' => 'draft',
            'version' => 1,
            'ai_score' => 0.8,
        ]);
    }

    private function makeReferenceAsset(?User $user = null): Asset
    {
        $user ??= $this->user;
        $path = 'assets/'.$user->id.'/reference.png';

        Storage::disk('public')->put($path, (string) base64_decode(self::PIXEL, true));

        return Asset::create([
            'user_id' => $user->id,
            'type' => 'image',
            'file_name' => 'reference.png',
            'file_size' => 68,
            'mime_type' => 'image/png',
            'storage_path' => $path,
            'storage_disk' => 'public',
            'ai_tags' => [],
        ]);
    }

    private function fakeOpenAi(): void
    {
        config(['services.ai.image.openai.api_key' => 'platform-key']);

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => self::PIXEL]]]),
        ]);
    }

    public function test_a_library_asset_can_be_used_as_the_reference(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();
        $reference = $this->makeReferenceAsset();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'instruction' => 'Place it on marble.',
            'provider' => 'openai',
            'reference_asset_id' => $reference->id,
        ])->assertAccepted()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.reference_asset_id', $reference->id);

        // A reference means the edits endpoint, carrying the image as multipart.
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/images/edits'
                && in_array('image[]', array_column($request->data(), 'name'), true);
        });

        $this->assertNotEmpty($package->fresh()->media_urls);
    }

    public function test_an_uploaded_reference_is_stored_in_the_library_and_used(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();

        $this->post("/api/content-packages/{$package->id}/generate-image", [
            'instruction' => 'Place it on marble.',
            'provider' => 'openai',
            'reference' => UploadedFile::fake()->image('my-product.png', 32, 32),
        ], ['Accept' => 'application/json'])->assertAccepted();

        $reference = Asset::query()
            ->where('user_id', $this->user->id)
            ->where('file_name', 'my-product.png')
            ->first();

        $this->assertNotNull($reference);
        $this->assertSame(['reference'], $reference->ai_tags);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/images/edits');
    }

    public function test_supplying_both_a_reference_upload_and_asset_id_is_rejected(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();
        $reference = $this->makeReferenceAsset();

        $this->post("/api/content-packages/{$package->id}/generate-image", [
            'reference_asset_id' => $reference->id,
            'reference' => UploadedFile::fake()->image('other.png'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_another_users_asset_cannot_be_used_as_a_reference(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();
        $foreign = $this->makeReferenceAsset(User::factory()->create());

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'reference_asset_id' => $foreign->id,
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_non_image_reference_is_rejected_by_validation(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();

        $this->post("/api/content-packages/{$package->id}/generate-image", [
            'reference' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_without_a_reference_the_text_only_path_is_still_used(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'instruction' => 'Minimal product flat lay on marble.',
            'provider' => 'openai',
        ])->assertAccepted();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/images/generations');
    }

    public function test_a_users_default_provider_is_used_when_none_is_supplied(): void
    {
        config([
            'services.ai.image.driver' => 'stub',
            'services.ai.image.gemini.api_key' => 'platform-gemini-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['inline_data' => ['mime_type' => 'image/png', 'data' => self::PIXEL]]]]]],
            ]),
        ]);

        $this->user->update(['ai_image_settings' => ['default_provider' => 'gemini']]);

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image")
            ->assertAccepted()
            ->assertJsonPath('data.provider', 'gemini');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    public function test_an_explicit_provider_overrides_the_saved_default(): void
    {
        $this->fakeOpenAi();
        $this->user->update(['ai_image_settings' => ['default_provider' => 'gemini']]);

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", ['provider' => 'openai'])
            ->assertAccepted()
            ->assertJsonPath('data.provider', 'openai');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
    }

    public function test_a_byok_key_is_used_instead_of_the_platform_key(): void
    {
        $this->fakeOpenAi();

        AiProviderCredential::create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'api_key' => 'sk-user-own-key',
        ]);

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", ['provider' => 'openai'])
            ->assertAccepted();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-user-own-key'));

        $signal = LearningSignal::query()->where('action', 'image_generated')->latest('id')->first();
        $this->assertSame('byok', $signal->metadata['key_source']);
        $this->assertSame('openai', $signal->metadata['provider']);
    }

    public function test_an_unconfigured_provider_is_refused_before_the_job_runs(): void
    {
        config([
            'services.ai.image.flux.api_key' => null,
            'services.ai.image.driver' => 'stub',
        ]);

        Http::fake();

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", ['provider' => 'flux'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
        $this->assertEmpty($package->fresh()->media_urls);
    }

    public function test_an_unknown_provider_is_rejected_by_validation(): void
    {
        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", ['provider' => 'midjourney'])
            ->assertStatus(422);
    }

    public function test_the_stub_provider_works_with_no_keys_configured(): void
    {
        config([
            'services.ai.image.driver' => 'stub',
            'services.ai.image.openai.api_key' => null,
            'services.ai.image.flux.api_key' => null,
            'services.ai.image.gemini.api_key' => null,
        ]);

        $package = $this->makePackage();
        $reference = $this->makeReferenceAsset();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'reference_asset_id' => $reference->id,
        ])->assertAccepted()
            ->assertJsonPath('data.provider', 'stub');

        $this->assertNotEmpty($package->fresh()->media_urls);
    }

    public function test_the_prompt_preview_endpoint_returns_the_composed_prompt(): void
    {
        $package = $this->makePackage();

        $response = $this->postJson("/api/content-packages/{$package->id}/image-prompt-preview", [
            'instruction' => 'Place it on marble.',
        ])->assertOk();

        $prompt = $response->json('data.prompt');
        $this->assertIsString($prompt);
        $this->assertStringContainsString('Place it on marble.', $prompt);
        $this->assertStringContainsString('Create a social media image for instagram', $prompt);
        $this->assertStringContainsString($package->caption, $prompt);
    }

    public function test_the_prompt_preview_endpoint_reflects_a_reference_image(): void
    {
        $package = $this->makePackage();
        $reference = $this->makeReferenceAsset();

        $response = $this->postJson("/api/content-packages/{$package->id}/image-prompt-preview", [
            'reference_asset_id' => $reference->id,
        ])->assertOk();

        $this->assertStringContainsString(
            'Use the supplied reference image as the basis',
            $response->json('data.prompt'),
        );
    }

    public function test_the_prompt_preview_endpoint_is_forbidden_for_another_users_package(): void
    {
        $foreignPackage = $this->makePackage(User::factory()->create());

        $this->postJson("/api/content-packages/{$foreignPackage->id}/image-prompt-preview")
            ->assertForbidden();
    }

    public function test_a_prompt_override_is_used_verbatim_and_persisted_on_the_asset(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();
        $override = 'A hand-picked, verbatim prompt straight from the user.';

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'provider' => 'openai',
            'instruction' => 'This should be ignored in favour of the override.',
            'prompt' => $override,
        ])->assertAccepted();

        Http::assertSent(function ($request) use ($override) {
            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && ($request->data()['prompt'] ?? null) === $override;
        });

        $asset = Asset::query()->where('user_id', $this->user->id)->latest('id')->first();

        $this->assertNotNull($asset);
        $this->assertSame($override, $asset->prompt);
    }

    public function test_without_an_override_the_composed_prompt_is_persisted_on_the_asset(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'provider' => 'openai',
            'instruction' => 'Minimal product flat lay on marble.',
        ])->assertAccepted();

        $asset = Asset::query()->where('user_id', $this->user->id)->latest('id')->first();

        $this->assertNotNull($asset);
        $this->assertStringContainsString('Minimal product flat lay on marble.', $asset->prompt);
        $this->assertStringContainsString('Create a social media image for instagram', $asset->prompt);
    }

    public function test_an_explicit_model_overrides_the_providers_default(): void
    {
        $this->fakeOpenAi();

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'provider' => 'openai',
            'model' => 'gpt-image-1',
        ])->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && ($request->data()['model'] ?? null) === 'gpt-image-1';
        });
    }

    public function test_a_users_saved_default_model_is_used_when_none_is_supplied(): void
    {
        $this->fakeOpenAi();
        $this->user->update(['ai_image_settings' => ['default_provider' => 'openai', 'default_model' => 'gpt-image-1']]);

        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image")
            ->assertAccepted();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && ($request->data()['model'] ?? null) === 'gpt-image-1';
        });
    }

    public function test_an_unknown_model_is_rejected_by_validation(): void
    {
        $package = $this->makePackage();

        $this->postJson("/api/content-packages/{$package->id}/generate-image", [
            'provider' => 'openai',
            'model' => 'not-a-real-model',
        ])->assertStatus(422);
    }
}
