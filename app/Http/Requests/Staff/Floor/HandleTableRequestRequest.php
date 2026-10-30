<?php

namespace App\Http\Requests\Staff\Floor;

use Illuminate\Foundation\Http\FormRequest;

class HandleTableRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('handle', $this->route('tableRequest')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
