<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialCredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'provider'       => $this->provider,
            'account_id'     => $this->account_id,
            'account_label'  => $this->account_label,
            'status'         => $this->status,
            'expires_at'     => $this->expires_at,
            'last_synced_at' => $this->feeds()->max('last_synced_at'),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
