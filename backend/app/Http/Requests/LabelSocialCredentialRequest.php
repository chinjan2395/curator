<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabelSocialCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_label' => ['required', 'string', 'max:255'],
        ];
    }
}
