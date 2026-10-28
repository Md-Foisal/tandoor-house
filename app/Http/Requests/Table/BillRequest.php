<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

class BillRequest extends FormRequest
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
            'service' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:even,items'],
            'people' => ['nullable', 'integer', 'min:2', 'max:12'],
            'guests' => ['nullable', 'integer', 'min:2', 'max:12'],
            'claims' => ['nullable', 'array'],
            'claims.*' => ['nullable', 'integer', 'min:0', 'max:11'],
        ];
    }
}
