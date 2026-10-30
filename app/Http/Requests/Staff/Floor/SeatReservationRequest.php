<?php

namespace App\Http\Requests\Staff\Floor;

use Illuminate\Foundation\Http\FormRequest;

class SeatReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.reservations.seat', $this->route('reservation')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'table_id' => ['nullable', 'integer', 'exists:dining_tables,id'],
        ];
    }
}
