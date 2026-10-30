<?php

namespace App\Http\Requests\Staff\Team;

use Illuminate\Foundation\Http\FormRequest;

class CopyWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.team') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['week' => ['required', 'date_format:Y-m-d']];
    }
}
