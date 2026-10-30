<?php

namespace App\Http\Requests\Staff\Inventory;

use App\Models\Ingredient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('logWaste', Ingredient::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reason' => ['required', Rule::in(['over_proofed', 'dropped', 'spoiled', 'over_prepped', 'returned_dish', 'other'])],
            'note' => [Rule::requiredIf(fn () => $this->input('reason') === 'other'), 'nullable', 'string', 'max:160'],
        ];
    }
}
