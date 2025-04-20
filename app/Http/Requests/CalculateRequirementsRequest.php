<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateRequirementsRequest extends FormRequest
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
        return [
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'A target item ID is required.',
            'item_id.integer' => 'The target item ID must be an integer.',
            'item_id.exists' => 'The selected target item does not exist.',
            'quantity.required' => 'A target quantity is required.',
            'quantity.numeric' => 'The target quantity must be a number.',
            'quantity.gt' => 'The target quantity must be greater than zero.',
        ];
    }
}
