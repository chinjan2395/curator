<?php

namespace Tests\Unit;

use App\DTOs\AuthData;
use App\DTOs\FeedData;
use App\DTOs\PostUpdateData;
use PHPUnit\Framework\TestCase;

class DTOsTest extends TestCase
{
    public function test_feed_data_from_array_maps_fields(): void
    {
        $dto = FeedData::fromArray([
            'name' => 'My Feed',
            'type' => 'youtube',
            'source_url' => 'https://example.com/feed',
            'social_credential_id' => '12',
            'youtube_channel_id' => 'UC123',
            'youtube_display_label' => '@channel',
            'facebook_page_id' => 'fb_1',
            'instagram_business_account_id' => 'ig_1',
            'instagram_username' => 'insta',
            'twitter_username' => 'xuser',
        ]);

        $this->assertSame('My Feed', $dto->name);
        $this->assertSame('youtube', $dto->type);
        $this->assertSame('https://example.com/feed', $dto->sourceUrl);
        $this->assertSame(12, $dto->socialCredentialId);
        $this->assertSame('UC123', $dto->youtubeChannelId);
        $this->assertSame('@channel', $dto->youtubeDisplayLabel);
        $this->assertSame('fb_1', $dto->facebookPageId);
        $this->assertSame('ig_1', $dto->instagramBusinessAccountId);
        $this->assertSame('insta', $dto->instagramUsername);
        $this->assertSame('xuser', $dto->twitterUsername);
    }

    public function test_post_update_data_handles_nullable_values(): void
    {
        $dto = PostUpdateData::fromArray([
            'status' => 'approved',
            'pinned' => '1',
        ]);
        $this->assertSame('approved', $dto->status);
        $this->assertTrue($dto->pinned);

        $dtoWithoutPinned = PostUpdateData::fromArray(['status' => 'rejected']);
        $this->assertSame('rejected', $dtoWithoutPinned->status);
        $this->assertNull($dtoWithoutPinned->pinned);
    }

    public function test_auth_data_from_array(): void
    {
        $dto = AuthData::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
        ]);

        $this->assertSame('Jane', $dto->name);
        $this->assertSame('jane@example.com', $dto->email);
        $this->assertSame('secret', $dto->password);
    }
}
