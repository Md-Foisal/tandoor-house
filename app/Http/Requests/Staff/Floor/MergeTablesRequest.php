<?php

namespace App\Http\Requests\Staff\Floor;

use Illuminate\Foundation\Http\FormRequest;

class MergeTablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.floor') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tables' => ['required', 'array', 'min:2', 'max:8'],
            'tables.*' => ['integer', 'distinct', 'exists:dining_tables,id'],
            'lead_id' => ['nullable', 'integer', 'exists:dining_tables,id'],
        ];
    }
}
