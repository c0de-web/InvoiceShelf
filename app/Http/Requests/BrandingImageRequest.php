<?php

namespace App\Http\Requests;

use App\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The header logo and app icon uploads: PNG or JPG only (an SVG served from our origin could run script).
 */
class BrandingImageRequest extends FormRequest
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
            'header_logo' => [
                'nullable',
                new Base64Mime(['jpg', 'png']),
            ],
            'app_icon' => [
                'nullable',
                new Base64Mime(['jpg', 'png']),
            ],
        ];
    }
}
