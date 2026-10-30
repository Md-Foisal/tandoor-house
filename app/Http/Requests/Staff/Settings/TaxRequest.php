<?php

namespace App\Http\Requests\Staff\Settings;

use App\Models\Setting;

class TaxRequest extends SettingsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateMoney', Setting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'vat_rate' => ['required', 'integer', 'min:0', 'max:5000'],
            'service_charge_rate' => ['required', 'integer', 'min:0', 'max:2500'],
            'tip_presets' => ['required', 'array', 'min:1', 'max:6'],
            'tip_presets.*' => ['required', 'integer', 'min:0', 'max:5000', 'distinct'],
            'deposit_per_head' => ['required', 'integer', 'min:0', 'max:10000'],
            'deposit_from_party' => ['required', 'integer', 'min:2', 'max:50'],
        ];
    }
}
