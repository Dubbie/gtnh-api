<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListItemsRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Define fields allowed for sorting via Spatie Query Builder
        $allowedSorts = [
            'name',
            'created_at',
            'updated_at', // Ascending
            '-name',
            '-created_at',
            '-updated_at' // Descending
        ];

        return [
            // Pagination
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], // Add a reasonable max limit

            // Sorting (using Spatie Query Builder format)
            'sort' => ['sometimes', 'string', Rule::in($allowedSorts)],

            // Filtering (using Spatie Query Builder format filter[key]=value)
            'filter' => ['sometimes', 'array'],
            'filter.name' => ['sometimes', 'string', 'max:255'],
            'filter.is_raw_material' => ['sometimes', 'boolean'],

            // Includes (using Spatie Query Builder format ?include=relation1,relation2)
            'include' => ['sometimes', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'You cannot request more than :max items per page.',
            'sort.in' => 'Invalid sort field provided.',
            'filter.is_raw_material.boolean' => 'The is_raw_material filter must be true or false (or 1 or 0).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('filter.is_raw_material')) {
            $this->merge([
                'filter' => array_merge($this->filter ?? [], [
                    'is_raw_material' => filter_var($this->input('filter.is_raw_material'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]),
            ]);
        }
    }
}
