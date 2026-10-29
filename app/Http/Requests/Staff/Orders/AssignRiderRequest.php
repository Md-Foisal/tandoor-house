<?php

namespace App\Http\Requests\Staff\Orders;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.orders') ?? false;
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
