<?php

namespace App\Http\Requests\Staff\Deliveries;

use Illuminate\Foundation\Http\FormRequest;

class FailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.deliveries') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:240']];
    }
}
