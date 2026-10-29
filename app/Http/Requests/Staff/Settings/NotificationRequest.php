<?php

namespace App\Http\Requests\Staff\Settings;

class NotificationRequest extends SettingsRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mail' => ['required', 'boolean'],
            'sms' => ['required', 'boolean'],
            'whatsapp' => ['sometimes', 'boolean', 'declined'],
            'owner_email' => ['nullable', 'email:rfc', 'max:190'],
        ];
    }
}
