<?php

namespace App\Http\Requests\Staff\Menu;

use Illuminate\Foundation\Http\FormRequest;

class MoveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', $this->route('category')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['direction' => ['required', 'in:up,down']];
    }
}
