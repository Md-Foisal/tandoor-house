<?php

namespace App\Http\Requests\Staff\Inventory;

use App\Models\Ingredient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($ingredient = $this->route('ingredient')) instanceof Ingredient
            ? ($this->user()?->can('update', $ingredient) ?? false)
            : ($this->user()?->can('create', Ingredient::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['required', Rule::in(['g', 'ml', 'pc'])],
            'low_threshold' => ['required', 'integer', 'min:0', 'max:10000000'],
            'par_level' => ['required', 'integer', 'min:1', 'max:10000000', 'gte:low_threshold'],
            'cost_per_thousand' => ['required', 'integer', 'min:0', 'max:100000000'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }
}
