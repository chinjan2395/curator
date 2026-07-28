<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'owner_id'         => $this->owner_id,
            'owner_name'       => $this->whenLoaded('owner', fn () => $this->owner?->name),
            'owner_email'      => $this->whenLoaded('owner', fn () => $this->owner?->email),
            'is_owner'         => $request->user() ? (int) $this->owner_id === (int) $request->user()->id : null,
            'public_key'       => $this->public_key,
            'last_published_at' => $this->last_published_at,
            'publish_settings' => $this->publish_settings,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
