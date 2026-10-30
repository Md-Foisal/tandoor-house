<?php

namespace App\Http\Requests\Staff\Team;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
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
        $roles = array_map(fn (Role $role) => $role->value, array_filter(Role::cases(), fn (Role $role) => $role->isStaff()));

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'role' => ['required', Rule::in($roles)],
        ];
    }
}
