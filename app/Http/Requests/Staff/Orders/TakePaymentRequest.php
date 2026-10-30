<?php

namespace App\Http\Requests\Staff\Orders;

use App\Models\Order;
use App\Policies\Staff\OrderBoardPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TakePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return ($this->user()?->can('staff.orders') ?? false)
            && $order instanceof Order
            && app(OrderBoardPolicy::class)->takePayment($this->user(), $order)->allowed();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['method' => ['required', Rule::in(['cash', 'card'])]];
    }
}
