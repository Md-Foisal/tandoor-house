<?php

namespace App\Http\Requests\Staff\Team;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'role' => ['sometimes', Rule::in($roles)],
        ];
    }
}
