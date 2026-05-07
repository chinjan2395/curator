<?php

namespace Tests\Unit;

use App\DTOs\PostUpdateData;
use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PostServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_update_post_updates_payload_and_returns_fresh_model(): void
    {
        Mockery::mock('alias:App\Models\ActivityLog')
            ->shouldReceive('create')
            ->atLeast()
            ->once();

        $post = Mockery::mock(Post::class);
        $freshPost = Mockery::mock(Post::class);
        $post->shouldReceive('update')->once()->with(['status' => 'approved', 'pinned' => true]);
        $post->shouldReceive('fresh')->once()->andReturn($freshPost);

        $feed = new Feed();
        $feed->name = 'Feed A';
        $user = new User();
        $user->id = 5;

        $service = new PostService();
        $dto = PostUpdateData::fromArray(['status' => 'approved', 'pinned' => true]);
        $result = $service->updatePost($post, $feed, $dto, $user);

        $this->assertSame($freshPost, $result);
    }
}
