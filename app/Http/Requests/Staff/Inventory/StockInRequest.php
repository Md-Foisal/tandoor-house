<?php

namespace App\Http\Requests\Staff\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stockIn', $this->route('ingredient')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
