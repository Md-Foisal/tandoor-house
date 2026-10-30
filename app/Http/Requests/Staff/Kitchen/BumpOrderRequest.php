<?php

namespace App\Http\Requests\Staff\Kitchen;

use App\Enums\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BumpOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.kitchen') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'station' => ['required', 'string', Rule::in(['all', ...array_column(Station::cases(), 'value')])],
        ];
    }
}
