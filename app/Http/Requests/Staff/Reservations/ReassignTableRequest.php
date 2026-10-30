<?php

namespace App\Http\Requests\Staff\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class ReassignTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.reservations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dining_table_id' => ['required', 'integer', 'exists:dining_tables,id'],
        ];
    }
}
