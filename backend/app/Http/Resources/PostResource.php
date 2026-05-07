<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'feed_id'       => $this->feed_id,
            'external_id'   => $this->external_id,
            'title'         => $this->title,
            'content'       => $this->content,
            'thumbnail_url' => $this->thumbnail_url,
            'video_url'     => $this->video_url,
            'status'        => $this->status,
            'pinned'        => $this->pinned,
            'posted_at'     => $this->posted_at,
            'published_at'  => $this->published_at,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
