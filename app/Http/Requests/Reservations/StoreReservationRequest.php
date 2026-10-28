<?php

namespace App\Http\Requests\Reservations;

use App\Actions\UkFormats;
use App\Services\TableAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public const OCCASIONS = ['birthday', 'anniversary', 'business', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        $email = $this->input('email');

        $this->merge([
            'phone' => is_string($phone) ? (UkFormats::mobile($phone) ?? $phone) : null,
            'email' => is_string($email) ? strtolower(trim($email)) : null,
            'occasion' => $this->input('occasion') === '' ? null : $this->input('occasion'),
        ]);
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
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'regex:/^\+447[1-57-9]\d{8}$/'],
            'occasion' => ['nullable', Rule::in(self::OCCASIONS)],
            'notes' => ['nullable', 'string', 'max:500'],
            'marketing_opt_in' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_size.required' => __('booking.errors.party_required'),
            'party_size.integer' => __('booking.errors.party_required'),
            'party_size.min' => __('booking.errors.party_required'),
            'party_size.max' => __('booking.errors.party_max', [
                'max' => app(TableAvailability::class)->maxParty(),
                'phone' => config('restaurant.contact.phone', ''),
            ]),
            'date.required' => __('booking.errors.date'),
            'date.date_format' => __('booking.errors.date'),
            'time.required' => __('booking.errors.time'),
            'time.date_format' => __('booking.errors.time'),
            'name.required' => __('booking.errors.name'),
            'email.required' => __('booking.errors.email'),
            'email.email' => __('booking.errors.email'),
            'phone.required' => __('booking.errors.phone'),
            'phone.regex' => __('booking.errors.phone'),
            'occasion.in' => __('booking.errors.occasion'),
            'notes.max' => __('booking.errors.notes'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkSlot($validator)];
    }

    public function startsAt(): ?CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->input('date').' '.$this->input('time'), config('app.timezone'));

        return $start;
    }

    private function checkSlot(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['party_size', 'date', 'time'])) {
            return;
        }

        $availability = app(TableAvailability::class);
        $start = $this->startsAt();

        if ($start === null || ! $availability->isBookableTime($start)) {
            $validator->errors()->add('time', __('booking.errors.time_unavailable'));

            return;
        }

        if ($availability->findTable($start, (int) $this->input('party_size')) === null) {
            $validator->errors()->add('time', __('booking.errors.taken'));
        }
    }
}
