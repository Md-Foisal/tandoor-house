<?php

namespace App\Http\Requests\Staff\Orders;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.orders.move', $this->route('order')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $needsReason = in_array($this->input('status'), [OrderStatus::Rejected->value, OrderStatus::Cancelled->value], true);

        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => [$needsReason ? 'required' : 'nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => __('staff.orders.reason_required')];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->string('status')->toString());
    }
}
