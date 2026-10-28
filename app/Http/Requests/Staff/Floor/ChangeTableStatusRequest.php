<?php

namespace App\Http\Requests\Staff\Floor;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.floor') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TableStatus::class)],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:40'],
        ];
    }
}
