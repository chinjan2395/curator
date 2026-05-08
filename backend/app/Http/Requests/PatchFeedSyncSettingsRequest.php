<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatchFeedSyncSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auto_publish_new_posts' => ['required', 'boolean'],
        ];
    }
}
