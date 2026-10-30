<?php

namespace App\Http\Requests\Staff\Reservations;

use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Services\TableAvailability;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A booking taken over the phone or at the door: same availability rules as
 * the public form, but the email is optional and managers can waive the deposit.
 */
class StoreStaffReservationRequest extends StoreReservationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.reservations.book') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = app(TableAvailability::class)->maxParty();

        return [
            'party_size' => ['required', 'integer', 'min:1', 'max:'.$max],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['required', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'occasion' => ['nullable', Rule::in(self::OCCASIONS)],
            'notes' => ['nullable', 'string', 'max:500'],
            'waive_deposit' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator) {
                if ($this->boolean('waive_deposit') && ! $this->user()?->role->isManagement()) {
                    $validator->errors()->add('waive_deposit', __('staff_reservations.errors.waive_managers_only'));
                }
            },
        ];
    }

    public function wantsDepositWaived(): bool
    {
        return $this->boolean('waive_deposit') && (bool) $this->user()?->role->isManagement();
    }
}
