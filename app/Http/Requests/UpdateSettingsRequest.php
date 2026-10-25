<?php

namespace App\Http\Requests;

use App\Support\BrandColor;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'required',
            ],
            'settings.brand_color' => [
                'nullable',
                'string',
                'regex:/^#[0-9a-fA-F]{6}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (BrandColor::isValid($value)
                        && BrandColor::contrastWithWhite($value) < BrandColor::MINIMUM_CONTRAST_WITH_WHITE) {
                        $fail('White text is hard to read on this colour. Choose a darker shade.');
                    }
                },
            ],
        ];
    }

    /**
     * A cleared brand colour is stored empty (the column is not nullable), which means "use the default".
     */
    protected function passedValidation(): void
    {
        $settings = $this->input('settings');

        if (is_array($settings) && array_key_exists('brand_color', $settings) && $settings['brand_color'] === null) {
            $settings['brand_color'] = '';
            $this->merge(['settings' => $settings]);
        }
    }
}
