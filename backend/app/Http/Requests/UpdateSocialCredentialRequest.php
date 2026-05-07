<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'      => ['sometimes', 'string', 'max:64'],
            'access_token'  => ['sometimes', 'string', 'max:2048'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
            'expires_at'    => ['nullable', 'date'],
        ];
    }
}
