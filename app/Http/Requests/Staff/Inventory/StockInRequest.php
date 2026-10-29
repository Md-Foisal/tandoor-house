<?php

namespace App\Http\Requests\Staff\Inventory;

use App\Policies\InventoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (new InventoryPolicy)->log($this->user());
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
