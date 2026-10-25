<?php

namespace App\Http\Requests;

use App\Models\ItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ItemCategoryRequest extends FormRequest
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
        $companyId = $this->header('company');
        $category = $this->route('item_category');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('item_categories')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($category?->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('item_categories', 'id')->where('company_id', $companyId),
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * Keeps the tree two levels deep: a parent must be a top-level category, and a
     * category that has subcategories stays top-level itself.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parentId = $this->input('parent_id');
                $category = $this->route('item_category');

                if (! $parentId || $validator->errors()->has('parent_id')) {
                    return;
                }

                if ($category && (int) $parentId === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');
                } elseif (ItemCategory::whereKey($parentId)->whereNotNull('parent_id')->exists()) {
                    $validator->errors()->add('parent_id', 'Subcategories cannot have subcategories of their own.');
                } elseif ($category && $category->children()->exists()) {
                    $validator->errors()->add('parent_id', 'This category has subcategories, so it cannot go under another category.');
                }
            },
        ];
    }

    public function getItemCategoryPayload(): array
    {
        return [
            ...$this->safe()->only(['name', 'parent_id', 'description']),
            'company_id' => $this->header('company'),
        ];
    }
}
