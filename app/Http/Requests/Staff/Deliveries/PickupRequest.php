<?php

namespace App\Http\Requests\Staff\Deliveries;

use App\Models\Order;
use App\Policies\DeliveryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class PickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return ($this->user()?->can('staff.deliveries') ?? false)
            && app(DeliveryPolicy::class)->act($this->user(), $order);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
