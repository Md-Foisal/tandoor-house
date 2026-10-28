<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

class SendOrderRequest extends FormRequest
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
        return [];
    }
}
