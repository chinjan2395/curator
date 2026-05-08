<?php

namespace App\DTOs;

class FeedData
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $sourceUrl,
        public readonly ?int $socialCredentialId,
        public readonly ?string $youtubeChannelId,
        public readonly ?string $youtubeDisplayLabel,
        public readonly ?string $facebookPageId,
        public readonly ?string $instagramBusinessAccountId,
        public readonly ?string $instagramUsername,
        public readonly ?string $twitterUsername,
        public readonly bool $autoPublishNewPosts = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:                          $data['name'],
            type:                          $data['type'],
            sourceUrl:                     $data['source_url'] ?? null,
            socialCredentialId:            isset($data['social_credential_id']) ? (int) $data['social_credential_id'] : null,
            youtubeChannelId:              $data['youtube_channel_id'] ?? null,
            youtubeDisplayLabel:           $data['youtube_display_label'] ?? null,
            facebookPageId:                $data['facebook_page_id'] ?? null,
            instagramBusinessAccountId:    $data['instagram_business_account_id'] ?? null,
            instagramUsername:             $data['instagram_username'] ?? null,
            twitterUsername:               $data['twitter_username'] ?? null,
            autoPublishNewPosts:           filter_var($data['auto_publish_new_posts'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }
}
