<?php

namespace App\Http\Requests\Staff\Deliveries;

use App\Models\Order;
use App\Policies\DeliveryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeliverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.deliveries') ?? false;
    }

    /**
     * Pence, required only when the rider collects cash.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');

        return [
            'cash_collected' => [(new DeliveryPolicy)->collectsCash($order) ? 'required' : 'prohibited', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
