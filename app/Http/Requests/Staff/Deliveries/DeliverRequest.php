<?php

namespace App\Http\Requests\Staff\Deliveries;

use App\Models\Order;
use App\Policies\DeliveryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeliverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('staff.deliveries') ?? false)
            && app(DeliveryPolicy::class)->act($this->user(), $this->order());
    }

    /**
     * Pence handed over, required only when the rider collects cash.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash_collected' => [app(DeliveryPolicy::class)->collectsCash($this->order()) ? 'required' : 'prohibited', 'integer', 'min:0', 'max:100000'],
        ];
    }

    private function order(): Order
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $order;
    }
}
