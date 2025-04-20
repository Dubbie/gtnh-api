<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $recipeId = $this->route('id'); // Get recipe ID from route

        return [
            // Use Rule::unique to ignore current recipe ID
            'name' => ['nullable', 'string', 'max:255', Rule::unique('recipes')->ignore($recipeId)],
            'crafting_method_id' => ['required', 'integer', 'exists:crafting_methods,id'],
            'eu_per_tick' => ['nullable', 'integer', 'min:0'],
            'duration_ticks' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'], // Use sometimes for updates

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

    // Re-use the same 'after' validation logic as StoreRecipeRequest
    public function after(): array
    {
        // You can extract the 'after' logic into a Trait or Base Request
        // class if you prefer DRY, but for simplicity here we repeat it.
        return [
            function (Validator $validator) {
                // Check for duplicate input items
                $inputItemIds = collect($this->input('inputs', []))->pluck('input_item_id');
                if ($inputItemIds->count() !== $inputItemIds->unique()->count()) {
                    $validator->errors()->add('inputs', 'Duplicate input item detected.');
                }

                // Check for duplicate output items
                $outputItemIds = collect($this->input('outputs', []))->pluck('item_id');
                if ($outputItemIds->count() !== $outputItemIds->unique()->count()) {
                    $validator->errors()->add('outputs', 'Duplicate output item detected.');
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
}
