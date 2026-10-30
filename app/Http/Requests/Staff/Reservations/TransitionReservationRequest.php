<?php

namespace App\Http\Requests\Staff\Reservations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionReservationRequest extends FormRequest
{
    public const ACTIONS = ['confirm', 'seat', 'complete', 'no_show', 'cancel'];

    public function authorize(): bool
    {
        if (! ($this->user()?->can('staff.reservations') ?? false)) {
            return false;
        }

        return $this->input('action') !== 'confirm' || $this->user()->role->isManagement();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(self::ACTIONS)],
            'reason' => [Rule::requiredIf(in_array($this->input('action'), ['cancel', 'confirm'], true)), 'nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __($this->input('action') === 'confirm' ? 'staff_reservations.errors.reference_required' : 'staff_reservations.errors.reason_required'),
        ];
    }
}
