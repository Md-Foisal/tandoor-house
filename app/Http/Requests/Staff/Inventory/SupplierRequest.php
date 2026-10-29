<?php

namespace App\Http\Requests\Staff\Inventory;

use App\Policies\InventoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (new InventoryPolicy)->manage($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20'],
            'lead_days' => ['required', 'integer', 'min:0', 'max:30'],
        ];
    }
}
