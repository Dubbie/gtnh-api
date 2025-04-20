<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRecipesRequest extends FormRequest
{
    /**
     * Define fields allowed for sorting.
     * Making it a property allows reuse in rules() and messages().
     * @var array<int, string>
     */
    private array $allowedSorts = [
        'name',
        'eu_per_tick',
        'duration_ticks',
        'created_at',
        'updated_at', // Ascending
        '-name',
        '-eu_per_tick',
        '-duration_ticks',
        '-created_at',
        '-updated_at' // Descending
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request for listing recipes.
     */
    public function rules(): array
    {
        return [
            // Pagination
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Sorting - Use the class property
            'sort' => ['sometimes', 'string', Rule::in($this->allowedSorts)],

            // Filtering rules...
            'filter' => ['sometimes', 'array'],
            'filter.name' => ['sometimes', 'string', 'max:255'],
            'filter.crafting_method_id' => ['sometimes', 'integer', 'exists:crafting_methods,id'],
            'filter.is_default' => ['sometimes', 'boolean'],

            // Includes rules...
            'include' => ['sometimes', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'You cannot request more than :max recipes per page.',
            // Use the class property directly for the message
            'sort.in' => 'Invalid sort field provided. Allowed: ' . implode(', ', $this->allowedSorts),
            'filter.is_default.boolean' => 'The is_default filter must be true or false (or 1 or 0).',
            'filter.crafting_method_id.exists' => 'The selected crafting method does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('filter.is_default')) {
            $this->merge([
                'filter' => array_merge($this->filter ?? [], [
                    'is_default' => filter_var($this->input('filter.is_default'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]),
            ]);
        }
    }
}
