<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class TestFacebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
            'facebook_page_id'     => ['required', 'string', 'max:255'],
        ];
    }
}
