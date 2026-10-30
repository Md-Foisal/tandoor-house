<?php

namespace App\Http\Requests\Staff\Inventory;

use App\Policies\InventoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderLinesRequest extends FormRequest
{
    public function authorize(InventoryPolicy $policy): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('staff.inventory') && $policy->manage($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:60'],
            'lines.*.ingredient_id' => ['required', 'integer', 'distinct', 'exists:ingredients,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
        ];
    }
}
