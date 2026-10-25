<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemsRequest extends FormRequest
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
            'name' => [
                'required',
            ],
            'price' => [
                'required',
            ],
            'unit_id' => [
                'nullable',
            ],
            'description' => [
                'nullable',
            ],
            'item_category_id' => [
                'nullable',
                'integer',
                Rule::exists('item_categories', 'id')->where('company_id', $this->header('company')),
            ],
        ];
    }
}
