<?php

namespace App\Http\Requests\Staff\Orders;

use Illuminate\Foundation\Http\FormRequest;

class EditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.orders.edit', $this->route('order')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
