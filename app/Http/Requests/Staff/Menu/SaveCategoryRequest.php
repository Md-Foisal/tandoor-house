<?php

namespace App\Http\Requests\Staff\Menu;

use App\Enums\ServicePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'service_periods' => ['nullable', 'array'],
            'service_periods.*' => ['distinct', Rule::enum(ServicePeriod::class)],
        ];
    }
}
