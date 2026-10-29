<?php

namespace App\Http\Requests\Staff\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FixClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('staff.team');
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
