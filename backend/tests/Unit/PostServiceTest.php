<?php

namespace Tests\Unit;

use App\DTOs\PostUpdateData;
use App\Models\ActivityLog;
use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PostServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_update_post_updates_payload_and_returns_fresh_model(): void
    {
        $post = Mockery::mock(Post::class);
        $freshPost = Mockery::mock(Post::class);
        $post->shouldReceive('update')->once()->with(['status' => 'approved', 'pinned' => true]);
        $post->shouldReceive('fresh')->once()->andReturn($freshPost);
        // The service passes $post->id to the activity log entries.
        $post->shouldReceive('getAttribute')->with('id')->andReturn(7);

        $feed = new Feed;
        $feed->name = 'Feed A';
        $user = User::factory()->create();

        $service = new PostService;
        $dto = PostUpdateData::fromArray(['status' => 'approved', 'pinned' => true]);
        $result = $service->updatePost($post, $feed, $dto, $user);

        $this->assertSame($freshPost, $result);

        // Asserted against the real table rather than an `alias:` mock of
        // ActivityLog: an alias mock stays registered in the autoloader for the
        // rest of the process, so every later test that logs activity died with
        // "Call to a member function __call() on null".
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id]);
    }
}
