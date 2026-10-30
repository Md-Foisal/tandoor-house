<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

class PayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'whole' => ['nullable', 'boolean'],
            'amount' => ['required_unless:whole,1,true', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'service' => ['nullable', 'boolean'],
            'tip' => ['nullable', 'integer', 'min:0', 'max:50000'],
        ];
    }
}
