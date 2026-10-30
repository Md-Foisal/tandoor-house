<?php

namespace App\Http\Requests\Staff\Team;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

class CopyWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('copyWeek', Shift::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['week' => ['required', 'date_format:Y-m-d']];
    }
}
