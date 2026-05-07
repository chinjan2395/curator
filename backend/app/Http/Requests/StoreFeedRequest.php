<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                           => ['required', 'string', 'max:255'],
            'type'                           => ['required', 'string', 'max:64'],
            'source_url'                     => ['nullable', 'string', 'max:500'],
            'social_credential_id'           => ['nullable', 'integer', 'exists:social_credentials,id'],
            'youtube_channel_id'             => ['nullable', 'string', 'max:255'],
            'youtube_display_label'          => ['nullable', 'string', 'max:255'],
            'facebook_page_id'               => ['nullable', 'string', 'max:255'],
            'instagram_business_account_id'  => ['nullable', 'string', 'max:255'],
            'instagram_username'             => ['nullable', 'string', 'max:80'],
            'twitter_username'               => ['nullable', 'string', 'max:32'],
        ];
    }
}
