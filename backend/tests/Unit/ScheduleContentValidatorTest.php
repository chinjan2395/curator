<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use App\Support\ScheduleContentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleContentValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_instagram_text_only_fails(): void
    {
        $package = $this->packageFor('instagram', 'Caption only', null);

        $result = ScheduleContentValidator::validate($package, 'instagram');

        $this->assertFalse($result['valid']);
        $this->assertFalse(collect($result['checks'])->firstWhere('id', 'media_required')['passed']);
    }

    public function test_instagram_with_https_image_passes(): void
    {
        $package = $this->packageFor('instagram', 'Launch post', ['https://cdn.example.com/photo.jpg']);

        $result = ScheduleContentValidator::validate($package, 'instagram');

        $this->assertTrue($result['valid']);
    }

    public function test_platform_mismatch_fails(): void
    {
        $package = $this->packageFor('twitter', 'Tweet', null);

        $result = ScheduleContentValidator::validate($package, 'instagram');

        $this->assertFalse($result['valid']);
    }

    /** @param  list<string>|null  $media */
    private function packageFor(string $platform, string $caption, ?array $media): ContentPackage
    {
        $user = User::factory()->create();
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'status' => 'active',
        ]);

        return ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => $platform,
            'content_type' => 'post',
            'caption' => $caption,
            'media_urls' => $media,
            'status' => 'approved',
        ]);
    }
}
