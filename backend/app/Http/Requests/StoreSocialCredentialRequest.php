<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'      => ['required', 'string', 'max:64'],
            'access_token'  => ['required', 'string', 'max:2048'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
            'expires_at'    => ['nullable', 'date'],
        ];
    }
}
