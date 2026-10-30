<?php

namespace App\Http\Requests\Staff\Deliveries;

use App\Enums\Role;
use App\Models\Order;
use App\Policies\DeliveryPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return ($this->user()?->can('staff.deliveries') ?? false)
            && app(DeliveryPolicy::class)->assign($this->user(), $order);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rider_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Rider->value)],
        ];
    }
}
