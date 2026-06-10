<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class PublishServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_stats_uses_repository_counts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Publish WS',
            'public_key' => 'pub_key_123',
            'last_published_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);

        $repo = Mockery::mock(PostRepositoryInterface::class);
        $repo->shouldReceive('countApprovedForWorkspace')->once()->with($workspace)->andReturn(9);
        $repo->shouldReceive('countPublishedForWorkspace')->once()->with($workspace)->andReturn(4);
        $repo->shouldReceive('countPendingForWorkspace')->once()->with($workspace)->andReturn(5);

        $service = new PublishService($repo);
        $stats = $service->getStats($workspace);

        $this->assertSame(9, $stats['approved']);
        $this->assertSame(4, $stats['published']);
        $this->assertSame(5, $stats['pending']);
        $this->assertSame(0, $stats['embed_clicks']);
        $this->assertSame('pub_key_123', $stats['public_key']);
        $this->assertArrayHasKey('publish_settings', $stats);
    }
}
