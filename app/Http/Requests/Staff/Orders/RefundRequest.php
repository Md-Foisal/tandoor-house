<?php

namespace App\Http\Requests\Staff\Orders;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refund', [Payment::class, $this->route('order')]) ?? false;
    }

    /**
     * Amounts are pence. "full" refunds everything left, "amount" a typed
     * sum, "lines" the chosen quantities of order lines.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['full', 'amount', 'lines'])],
            'amount' => [Rule::requiredIf($this->input('kind') === 'amount'), 'nullable', 'integer', 'min:1'],
            'lines' => [Rule::requiredIf($this->input('kind') === 'lines'), 'nullable', 'array'],
            'lines.*' => ['integer', 'min:0', 'max:20'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => __('staff_orders.refund.reason_required')];
    }
}
