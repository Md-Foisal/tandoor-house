<?php

namespace App\Http\Requests\Staff\Reservations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionReservationRequest extends FormRequest
{
    public const ACTIONS = ['confirm', 'seat', 'complete', 'no_show', 'cancel'];

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
            'action' => ['required', Rule::in(self::ACTIONS)],
            'reason' => [Rule::requiredIf($this->input('action') === 'cancel'), 'nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('staff_reservations.errors.reason_required'),
        ];
    }
}
