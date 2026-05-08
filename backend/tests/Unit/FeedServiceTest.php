<?php

namespace Tests\Unit;

use App\DTOs\FeedData;
use App\Models\Feed;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\FeedRepositoryInterface;
use App\Services\FeedService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Mockery;
use PHPUnit\Framework\TestCase;

class FeedServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_create_feed_for_rss_calls_repository_with_payload(): void
    {
        $workspace = new Workspace();
        $user = new User();
        $user->id = 7;

        $dto = FeedData::fromArray([
            'name' => 'RSS Feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/rss.xml',
        ]);

        $expectedFeed = new Feed();
        $expectedFeed->id = 11;

        $repo = Mockery::mock(FeedRepositoryInterface::class);
        $repo->shouldReceive('create')
            ->once()
            ->with($workspace, Mockery::on(function (array $payload): bool {
                return $payload['name'] === 'RSS Feed'
                    && $payload['type'] === 'rss'
                    && $payload['source_url'] === 'https://example.com/rss.xml'
                    && $payload['social_credential_id'] === null
                    && isset($payload['auto_publish_new_posts'])
                    && $payload['auto_publish_new_posts'] === false;
            }))
            ->andReturn($expectedFeed);

        $service = new FeedService($repo);
        $result = $service->createFeed($workspace, $dto, $user);

        $this->assertSame($expectedFeed, $result);
    }

    public function test_create_feed_throws_for_invalid_rss_url(): void
    {
        $workspace = new Workspace();
        $user = new User();
        $user->id = 7;

        $dto = FeedData::fromArray([
            'name' => 'Bad RSS',
            'type' => 'rss',
            'source_url' => 'not-a-url',
        ]);

        $repo = Mockery::mock(FeedRepositoryInterface::class);
        $repo->shouldNotReceive('create');

        $service = new FeedService($repo);

        $this->expectException(HttpResponseException::class);
        $service->createFeed($workspace, $dto, $user);
    }
}
