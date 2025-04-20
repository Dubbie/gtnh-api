<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCraftingMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Define fields allowed for sorting
        $allowedSorts = [
            'name',
            'created_at',
            'updated_at',
            '-name',
            '-created_at',
            '-updated_at'
        ];

        return [
            // Pagination
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Sorting
            'sort' => ['sometimes', 'string', Rule::in($allowedSorts)],

            // Filtering
            'filter' => ['sometimes', 'array'],
            'filter.name' => ['sometimes', 'string', 'max:255'], // Only filter by name for this model

            // Includes
            'include' => ['sometimes', 'string'], // e.g., ?include=recipes
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.max' => 'You cannot request more than :max items per page.',
            'sort.in' => 'Invalid sort field provided.',
        ];
    }
}
