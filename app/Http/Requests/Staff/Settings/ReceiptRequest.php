<?php

namespace App\Http\Requests\Staff\Settings;

class ReceiptRequest extends SettingsRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['footer' => ['nullable', 'string', 'max:240']];
    }
}
