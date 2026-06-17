<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuplicateGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'match_type' => $this->match_type,
            'status'     => $this->status,
            'posts'      => PostResource::collection($this->whenLoaded('posts')),
        ];
    }
}
