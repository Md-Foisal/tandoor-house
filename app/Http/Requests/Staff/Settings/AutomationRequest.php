<?php

namespace App\Http\Requests\Staff\Settings;

use App\Http\Controllers\Staff\Settings\SettingsController;

class AutomationRequest extends SettingsRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return collect(SettingsController::AUTOMATIONS)
            ->mapWithKeys(fn (string $key) => [$key => ['required', 'boolean']])
            ->all();
    }
}
