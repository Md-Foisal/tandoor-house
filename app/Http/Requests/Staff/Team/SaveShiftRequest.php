<?php

namespace App\Http\Requests\Staff\Team;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveShiftRequest extends FormRequest
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
        $roles = array_map(fn (Role $role) => $role->value, array_filter(Role::cases(), fn (Role $role) => $role->isStaff()));

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'role' => ['required', Rule::in($roles)],
            'notes' => ['nullable', 'string', 'max:190'],
        ];
    }
}
