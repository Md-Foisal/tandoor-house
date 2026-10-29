<?php

namespace App\Http\Requests\Staff\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CopyWeekRequest extends FormRequest
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
        return ['week' => ['required', 'date_format:Y-m-d']];
    }
}
