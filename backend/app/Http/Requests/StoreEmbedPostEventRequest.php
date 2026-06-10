<?php

namespace App\Http\Requests;

use App\Models\EmbedPostEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmbedPostEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_type' => [
                'required',
                'string',
                Rule::in([
                    EmbedPostEvent::TYPE_POST_CLICK,
                    EmbedPostEvent::TYPE_SHARE_CLICK,
                ]),
            ],
            'target_url' => ['nullable', 'string', 'max:2048'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
