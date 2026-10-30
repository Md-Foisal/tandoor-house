<?php

namespace App\Http\Requests\Staff\Kitchen;

use Illuminate\Foundation\Http\FormRequest;

class SoldOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.kitchen') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
