<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
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
        $itemId = $this->route('id'); // Get item ID from route parameter

        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('items')->ignore($itemId),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('items')->ignore($itemId),
            ],
            'is_raw_material' => 'nullable|boolean',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:255',
        ];
    }
}
