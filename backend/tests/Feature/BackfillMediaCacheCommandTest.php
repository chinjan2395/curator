<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackfillMediaCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_caches_posts_missing_a_cached_thumbnail(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'backfill-public-key-1234567890',
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'IG',
            'type' => 'instagram',
            'source_url' => 'https://instagram.com/x',
        ]);

        $source = 'https://scontent.cdninstagram.com/v/t51/backfill.jpg?oe='.dechex(time() + 7200);
        $post = Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'IG post',
            'content' => 'Caption',
            'thumbnail_url' => $source,
            'video_url' => 'https://www.instagram.com/p/abc/',
            'posted_at' => now(),
            'external_id' => '17841400000000001',
            'status' => 'approved',
            'pinned' => false,
            'published_at' => now(),
        ]);

        Http::fake([
            $source => Http::response('backfilled-image', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('media:backfill-cache')->assertSuccessful();

        $post->refresh();
        $this->assertNotNull($post->cached_thumbnail_path);
        $this->assertTrue(Storage::disk('local')->exists($post->cached_thumbnail_path));
    }
}
