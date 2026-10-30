<?php

namespace App\Http\Requests\Staff\Inventory;

use App\Policies\InventoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class AdjustRequest extends FormRequest
{
    public function authorize(InventoryPolicy $policy): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('staff.inventory') && $policy->log($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted' => ['required', 'integer', 'min:0', 'max:10000000'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
