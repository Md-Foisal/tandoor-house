<?php

namespace App\Http\Requests\Staff\Deliveries;

use Illuminate\Foundation\Http\FormRequest;

class PickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.deliveries.act', $this->route('order')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
