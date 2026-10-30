<?php

namespace App\Http\Requests\Staff\Floor;

use Illuminate\Foundation\Http\FormRequest;

class TransferTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfer', $this->route('table')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'integer', 'exists:dining_tables,id'],
        ];
    }
}
