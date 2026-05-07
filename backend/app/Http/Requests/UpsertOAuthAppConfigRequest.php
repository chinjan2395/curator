<?php

namespace App\Http\Requests;

use App\Models\OAuthAppConfig;
use Illuminate\Foundation\Http\FormRequest;

class UpsertOAuthAppConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope'         => ['required', 'in:' . OAuthAppConfig::SCOPE_USER . ',' . OAuthAppConfig::SCOPE_SHARED],
            'provider'      => ['required', 'string', 'max:64'],
            'client_id'     => ['required', 'string', 'max:512'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'redirect_uri'  => ['nullable', 'string', 'max:1024'],
        ];
    }
}
