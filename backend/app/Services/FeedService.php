<?php

namespace App\Services;

use App\DTOs\FeedData;
use App\Models\Feed;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\FeedRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;

class FeedService
{
    private const CREDENTIAL_TYPES = ['youtube', 'facebook', 'instagram', 'twitter', 'tiktok', 'threads'];

    public function __construct(
        private readonly FeedRepositoryInterface $feedRepository,
    ) {}

    public function listForWorkspace(Workspace $workspace): \Illuminate\Database\Eloquent\Collection
    {
        return $this->feedRepository->allForWorkspace($workspace);
    }

    public function createFeed(Workspace $workspace, FeedData $dto, User $user): Feed
    {
        $data = $this->dtoToArray($dto);
        $this->validateProviderConstraints($data, $user->id);

        return $this->feedRepository->create($workspace, array_merge(
            $this->buildFeedPayload($data),
            ['auto_publish_new_posts' => $dto->autoPublishNewPosts],
        ));
    }

    public function updateFeed(Feed $feed, FeedData $dto, User $user): Feed
    {
        $data = $this->dtoToArray($dto);
        $this->validateProviderConstraints($data, $user->id);

        $payload = $this->buildFeedPayload($data, $feed, $feed->youtube_channel_id);

        return $this->feedRepository->update($feed, $payload);
    }

    private function dtoToArray(FeedData $dto): array
    {
        return [
            'name'                          => $dto->name,
            'type'                          => $dto->type,
            'source_url'                    => $dto->sourceUrl,
            'social_credential_id'          => $dto->socialCredentialId,
            'youtube_channel_id'            => $dto->youtubeChannelId,
            'youtube_display_label'         => $dto->youtubeDisplayLabel,
            'facebook_page_id'              => $dto->facebookPageId,
            'instagram_business_account_id' => $dto->instagramBusinessAccountId,
            'instagram_username'            => $dto->instagramUsername,
            'twitter_username'              => $dto->twitterUsername,
        ];
    }

    public function deleteFeed(Feed $feed): void
    {
        $this->feedRepository->delete($feed);
    }

    private function validateProviderConstraints(array $data, int $userId): void
    {
        $type = $data['type'];

        match ($type) {
            'youtube'   => $this->validateYoutube($data, $userId),
            'facebook'  => $this->validateFacebook($data, $userId),
            'instagram' => $this->validateInstagram($data, $userId),
            'twitter'   => $this->validateTwitter($data, $userId),
            'tiktok'    => $this->validateTiktok($data, $userId),
            'threads'   => $this->validateThreads($data, $userId),
            'rss'       => $this->validateRss($data),
            default     => null,
        };
    }

    private function validateYoutube(array $data, int $userId): void
    {
        if (empty($data['social_credential_id'])) {
            $this->abortWithMessage('Select a YouTube credential for this feed.');
        }
        if (empty($data['youtube_channel_id'])) {
            $this->abortWithMessage('Enter a YouTube channel ID or handle.');
        }
        if (! $this->feedRepository->hasProviderCredential((int) $data['social_credential_id'], $userId, 'youtube')) {
            $this->abortWithMessage('Select a valid YouTube credential.');
        }
    }

    private function validateFacebook(array $data, int $userId): void
    {
        if (empty($data['social_credential_id'])) {
            $this->abortWithMessage('Select a Facebook credential for this feed.');
        }
        if (trim((string) ($data['facebook_page_id'] ?? '')) === '') {
            $this->abortWithMessage('Enter your Facebook Page ID.');
        }
        if (! $this->feedRepository->hasProviderCredential((int) $data['social_credential_id'], $userId, 'facebook')) {
            $this->abortWithMessage('Select a valid Facebook credential.');
        }
    }

    private function validateInstagram(array $data, int $userId): void
    {
        if (empty($data['social_credential_id'])) {
            $this->abortWithMessage('Select an Instagram credential for this feed.');
        }
        if (trim((string) ($data['facebook_page_id'] ?? '')) === '') {
            $this->abortWithMessage('Select the Facebook Page linked to your Instagram account.');
        }
        if (trim((string) ($data['instagram_business_account_id'] ?? '')) === '') {
            $this->abortWithMessage('Select an Instagram Business account.');
        }
        if (! $this->feedRepository->hasProviderCredential((int) $data['social_credential_id'], $userId, 'instagram')) {
            $this->abortWithMessage('Select a valid Instagram credential.');
        }
    }

    private function validateTwitter(array $data, int $userId): void
    {
        if (empty($data['social_credential_id'])) {
            $this->abortWithMessage('Select a Twitter / X credential for this feed.');
        }
        if (! $this->feedRepository->hasProviderCredential((int) $data['social_credential_id'], $userId, 'twitter')) {
            $this->abortWithMessage('Select a valid Twitter / X credential.');
        }
    }

    private function validateTiktok(array $data, int $userId): void
    {
        if (empty($data['social_credential_id'])) {
            $this->abortWithMessage('Select a TikTok credential for this feed.');
        }
        if (! $this->feedRepository->hasProviderCredential((int) $data['social_credential_id'], $userId, 'tiktok')) {
            $this->abortWithMessage('Select a valid TikTok credential.');
        }
    }

    private function validateThreads(array $data, int $userId): void
    {
        if (empty($data['social_credential_id'])) {
            $this->abortWithMessage('Select a Threads credential for this feed.');
        }
        if (! $this->feedRepository->hasProviderCredential((int) $data['social_credential_id'], $userId, 'threads')) {
            $this->abortWithMessage('Select a valid Threads credential.');
        }
    }

    private function validateRss(array $data): void
    {
        $url = trim((string) ($data['source_url'] ?? ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->abortWithMessage('Enter a valid RSS URL.');
        }
    }

    private function buildFeedPayload(array $data, ?Feed $existing = null, ?string $originalChannelId = null): array
    {
        $type   = $data['type'];
        $igUser = $type === 'instagram' ? trim((string) ($data['instagram_username'] ?? '')) : '';
        $ytLabel = $type === 'youtube' ? trim((string) ($data['youtube_display_label'] ?? '')) : '';

        $sourceAccountLabel = $existing?->source_account_label;

        if ($type === 'instagram') {
            $sourceAccountLabel = $igUser !== '' ? '@' . ltrim($igUser, '@') : $sourceAccountLabel;
        } elseif ($type === 'youtube') {
            $channelChanged = ($data['youtube_channel_id'] ?? null) !== $originalChannelId;
            if ($ytLabel !== '') {
                $sourceAccountLabel = $ytLabel;
            } elseif ($channelChanged) {
                $sourceAccountLabel = null;
            }
        } elseif ($existing === null) {
            $sourceAccountLabel = null;
        }

        $payload = [
            'name'                           => $data['name'],
            'type'                           => $type,
            'source_url'                     => $data['source_url'] ?? '',
            'source_account_label'           => $sourceAccountLabel,
            'social_credential_id'           => in_array($type, self::CREDENTIAL_TYPES, true)
                ? ($data['social_credential_id'] ?? null)
                : null,
            'youtube_channel_id'             => $type === 'youtube' ? ($data['youtube_channel_id'] ?? null) : null,
            'facebook_page_id'               => match ($type) {
                'facebook', 'instagram' => trim((string) ($data['facebook_page_id'] ?? '')),
                default                 => null,
            },
            'instagram_business_account_id'  => $type === 'instagram'
                ? trim((string) ($data['instagram_business_account_id'] ?? ''))
                : null,
            'twitter_username'               => $type === 'twitter' ? ($existing?->twitter_username) : null,
        ];

        if ($type === 'youtube' && ($data['youtube_channel_id'] ?? null) !== $originalChannelId) {
            $payload['youtube_uploads_playlist_id'] = null;
        }

        return $payload;
    }

    private function abortWithMessage(string $message): never
    {
        throw new HttpResponseException(
            response()->json(['message' => $message], 422)
        );
    }
}
