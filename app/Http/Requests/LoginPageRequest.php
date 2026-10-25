<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginPageRequest extends FormRequest
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
            'login_page_heading' => ['nullable', 'string', 'max:120'],
            'login_page_description' => ['nullable', 'string', 'max:300'],
            'copyright_text' => ['nullable', 'string', 'max:120'],
        ];
    }
}
