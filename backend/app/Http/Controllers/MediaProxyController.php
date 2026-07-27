<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Post;
use App\Services\Media\MediaProxyService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaProxyController extends Controller
{
    public function __construct(
        private readonly MediaProxyService $mediaProxy,
    ) {}

    public function postThumbnail(Post $post): StreamedResponse
    {
        return $this->mediaProxy->streamPostThumbnail($post);
    }

    public function feedAvatar(Feed $feed): StreamedResponse
    {
        return $this->mediaProxy->streamFeedAvatar($feed);
    }
}
