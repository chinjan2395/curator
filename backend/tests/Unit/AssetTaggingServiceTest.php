<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\User;
use App\Services\AI\StubAiProvider;
use App\Services\Content\AssetTaggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssetTaggingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_json_tags_from_llm_response(): void
    {
        config(['services.ai.groq.api_key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '["launch","product-demo","b2b"]']],
                ],
            ]),
        ]);

        $asset = $this->makeAsset('Product Launch Hero.png', 'image');

        $tags = (new AssetTaggingService(new \App\Services\AI\GroqAiProvider))->suggestTags($asset);

        $this->assertSame(['launch', 'product-demo', 'b2b'], $tags);
    }

    public function test_stub_driver_falls_back_to_filename_tags(): void
    {
        $asset = $this->makeAsset('Team Photo 2026.jpg', 'image');

        $tags = (new AssetTaggingService(new StubAiProvider))->suggestTags($asset);

        $this->assertContains('team-photo-2026', $tags);
        $this->assertContains('image', $tags);
    }

    private function makeAsset(string $fileName, string $type): Asset
    {
        $user = User::factory()->create();

        return Asset::create([
            'user_id' => $user->id,
            'type' => $type,
            'file_name' => $fileName,
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'storage_path' => 'assets/'.$user->id.'/test.jpg',
            'ai_tags' => [],
        ]);
    }
}
