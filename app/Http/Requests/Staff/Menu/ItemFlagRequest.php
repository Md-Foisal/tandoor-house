<?php

namespace App\Http\Requests\Staff\Menu;

use Illuminate\Foundation\Http\FormRequest;

class ItemFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.menu') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['value' => ['required', 'boolean']];
    }
}
