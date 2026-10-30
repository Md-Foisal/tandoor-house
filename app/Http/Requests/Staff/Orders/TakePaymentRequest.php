<?php

namespace App\Http\Requests\Staff\Orders;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TakePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Payment::class, $this->route('order')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['method' => ['required', Rule::in(['cash', 'card'])]];
    }
}
