<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                             => $this->id,
            'workspace_id'                   => $this->workspace_id,
            'name'                           => $this->name,
            'type'                           => $this->type,
            'source_url'                     => $this->source_url,
            'source_account_label'           => $this->source_account_label,
            'social_credential_id'           => $this->social_credential_id,
            'youtube_channel_id'             => $this->youtube_channel_id,
            'youtube_uploads_playlist_id'    => $this->youtube_uploads_playlist_id,
            'facebook_page_id'               => $this->facebook_page_id,
            'instagram_business_account_id'  => $this->instagram_business_account_id,
            'twitter_username'               => $this->twitter_username,
            'last_synced_at'                 => $this->last_synced_at,
            'created_at'                     => $this->created_at,
            'updated_at'                     => $this->updated_at,
        ];
    }
}
