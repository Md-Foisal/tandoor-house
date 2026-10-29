<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\NotificationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function update(NotificationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $changed = SettingsChange::save($request->user(), 'notifications', [
            'notification_channels' => ['mail' => (bool) $data['mail'], 'sms' => (bool) $data['sms'], 'whatsapp' => false],
            'owner_email' => trim((string) ($data['owner_email'] ?? '')),
        ], 'Changed notification settings');

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.notifications' : 'staff_settings.toast.unchanged')]);

        return back();
    }
}
