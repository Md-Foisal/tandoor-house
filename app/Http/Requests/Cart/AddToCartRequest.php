<?php

namespace App\Http\Requests\Cart;

use App\Services\CartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')],
            'item_variant_id' => ['nullable', 'integer'],
            'modifier_ids' => ['array', 'max:20'],
            'modifier_ids.*' => ['integer', 'distinct'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:140'],
        ];
    }
}
