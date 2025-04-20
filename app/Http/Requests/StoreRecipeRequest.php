<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255', 'unique:recipes,name'],
            'crafting_method_id' => ['required', 'integer', 'exists:crafting_methods,id'],
            'eu_per_tick' => ['nullable', 'integer', 'min:0'],
            'duration_ticks' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'], // Use sometimes if default exists

            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.input_item_id' => ['required', 'integer', 'exists:items,id'],
            'inputs.*.input_quantity' => ['required', 'integer', 'min:1'],

            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'outputs.*.quantity' => ['required', 'integer', 'min:1'],
            'outputs.*.chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'outputs.*.is_primary_output' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     * Add custom validation logic after standard rules.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Check for duplicate input items
                $inputItemIds = collect($this->input('inputs', []))->pluck('input_item_id');
                if ($inputItemIds->count() !== $inputItemIds->unique()->count()) {
                    $validator->errors()->add(
                        'inputs', // Field key
                        'Duplicate input item detected. Each input item must be unique within a recipe.' // Error message
                    );
                }

                // Check for duplicate output items
                $outputItemIds = collect($this->input('outputs', []))->pluck('item_id');
                if ($outputItemIds->count() !== $outputItemIds->unique()->count()) {
                    $validator->errors()->add(
                        'outputs',
                        'Duplicate output item detected. Each output item must be unique within a recipe.'
                    );
                }

                // Ensure exactly one primary output
                $primaryOutputCount = collect($this->input('outputs', []))
                    ->where('is_primary_output', true)
                    ->count();
                if ($primaryOutputCount !== 1 && !empty($this->input('outputs', []))) {
                    $validator->errors()->add(
                        'outputs',
                        'Exactly one output must be marked as the primary output.'
                    );
                }
            }
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'inputs.*.input_item_id.required' => 'An input item is required.',
            'inputs.*.input_quantity.required' => 'An input quantity is required.',
            'outputs.*.item_id.required' => 'An output item is required.',
            'outputs.*.quantity.required' => 'An output quantity is required.',
            'outputs.*.chance.required' => 'An output chance is required.',
        ];
    }
}
