<?php

namespace App\Http\Requests\Staff\Team;

use Illuminate\Foundation\Http\FormRequest;

class FixClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fix', $this->route('attendance')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'clocked_out_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:3', 'max:190'],
        ];
    }
}
