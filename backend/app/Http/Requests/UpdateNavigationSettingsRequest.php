<?php

namespace App\Http\Requests;

use App\Support\NavigationMenuRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNavigationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $menuKeys = NavigationMenuRegistry::menuIds();
        $featureKeys = NavigationMenuRegistry::featureIds();

        return [
            'menus' => ['sometimes', 'array'],
            'menus.*' => ['boolean'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $menus = $this->input('menus');
        $features = $this->input('features');

        if (is_array($menus)) {
            $this->merge([
                'menus' => array_intersect_key($menus, array_flip(NavigationMenuRegistry::menuIds())),
            ]);
        }

        if (is_array($features)) {
            $this->merge([
                'features' => array_intersect_key($features, array_flip(NavigationMenuRegistry::featureIds())),
            ]);
        }
    }
}
