<?php

namespace App\Http\Resources;

use App\Support\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'avatar_url' => $this->avatar_url,
            'industry' => $this->industry,
            'target_audience' => $this->target_audience,
            'brand_voice' => $this->brand_voice,
            'is_onboarded' => (bool) $this->is_onboarded,
            'email_verified_at' => $this->email_verified_at,
            'email_verification_required' => EmailVerification::required(),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
