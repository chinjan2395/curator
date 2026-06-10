<?php

namespace App\Services\Social\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LinkedInPublishingClient
{
    private const API_BASE = 'https://api.linkedin.com';

    public function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get(self::API_BASE.'/v2/userinfo');

        return $this->okJson($response);
    }

    public function personUrn(string $personId): string
    {
        return 'urn:li:person:'.trim($personId);
    }

    public function createTextPost(string $accessToken, string $authorUrn, string $commentary): string
    {
        return $this->createPost($accessToken, [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => $this->distributionPayload(),
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]);
    }

    /**
     * @return array{upload_url: string, image_urn: string}
     */
    public function initializeImageUpload(string $accessToken, string $ownerUrn): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->withHeaders($this->apiHeaders())
            ->post(self::API_BASE.'/rest/images?action=initializeUpload', [
                'initializeUploadRequest' => [
                    'owner' => $ownerUrn,
                ],
            ]);

        if (! $response->ok()) {
            $this->throwApiError($response);
        }

        $value = $response->json('value');
        if (! is_array($value)) {
            throw new RuntimeException('LinkedIn image initialize returned no value.');
        }

        $uploadUrl = (string) ($value['uploadUrl'] ?? '');
        $imageUrn = (string) ($value['image'] ?? '');

        if ($uploadUrl === '' || $imageUrn === '') {
            throw new RuntimeException('LinkedIn image initialize returned incomplete upload data.');
        }

        return [
            'upload_url' => $uploadUrl,
            'image_urn' => $imageUrn,
        ];
    }

    public function uploadImageBinary(string $uploadUrl, string $binary, string $accessToken): void
    {
        $response = Http::withToken($accessToken)
            ->withBody($binary, 'application/octet-stream')
            ->timeout(120)
            ->put($uploadUrl);

        if (! $response->successful()) {
            throw new RuntimeException('LinkedIn image binary upload failed: '.$response->body());
        }
    }

    /**
     * @param  list<string>  $imageUrns
     */
    public function createImagePost(string $accessToken, string $authorUrn, string $commentary, array $imageUrns): string
    {
        if ($imageUrns === []) {
            throw new RuntimeException('LinkedIn image post requires at least one uploaded image.');
        }

        if (count($imageUrns) === 1) {
            return $this->createPost($accessToken, [
                'author' => $authorUrn,
                'commentary' => $commentary,
                'visibility' => 'PUBLIC',
                'distribution' => $this->distributionPayload(),
                'content' => [
                    'media' => [
                        'id' => $imageUrns[0],
                    ],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);
        }

        $images = array_map(static fn (string $urn) => ['id' => $urn], $imageUrns);

        return $this->createPost($accessToken, [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => $this->distributionPayload(),
            'content' => [
                'multiImage' => [
                    'images' => $images,
                ],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]);
    }

    public function createArticlePost(
        string $accessToken,
        string $authorUrn,
        string $commentary,
        string $sourceUrl,
        ?string $title = null,
        ?string $description = null,
    ): string {
        $article = ['source' => $sourceUrl];
        if ($title !== null && $title !== '') {
            $article['title'] = $title;
        }
        if ($description !== null && $description !== '') {
            $article['description'] = $description;
        }

        return $this->createPost($accessToken, [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => $this->distributionPayload(),
            'content' => ['article' => $article],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function distributionPayload(): array
    {
        return [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => [],
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function createPost(string $accessToken, array $body): string
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->withHeaders($this->apiHeaders())
            ->post(self::API_BASE.'/rest/posts', $body);

        if (! in_array($response->status(), [200, 201], true)) {
            $this->throwApiError($response);
        }

        $postId = (string) ($response->header('x-restli-id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('LinkedIn API returned no post id.');
        }

        return $postId;
    }

    /** @return array<string, string> */
    private function apiHeaders(): array
    {
        $version = (string) config('services.linkedin.api_version', '202505');

        return [
            'Content-Type' => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $version,
        ];
    }

    /** @return array<string, mixed> */
    private function okJson(Response $response): array
    {
        if (! $response->ok()) {
            $this->throwApiError($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function throwApiError(Response $response): void
    {
        $message = $response->json('message')
            ?? $response->json('error_description')
            ?? $response->json('error')
            ?? $response->body();

        throw new RuntimeException('LinkedIn API error: '.(is_string($message) ? $message : json_encode($message)));
    }
}
