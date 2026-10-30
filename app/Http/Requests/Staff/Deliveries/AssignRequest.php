<?php

namespace App\Http\Requests\Staff\Deliveries;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.deliveries.assign', $this->route('order')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rider_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Rider->value)],
        ];
    }
}
