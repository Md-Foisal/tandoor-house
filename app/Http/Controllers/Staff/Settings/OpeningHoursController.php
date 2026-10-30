<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\OpeningHoursRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OpeningHoursController extends Controller
{
    public function update(OpeningHoursRequest $request): RedirectResponse
    {
        $changed = SettingsChange::save($request->user(), 'hours', ['opening_hours' => $request->week()], __('staff_settings.log.hours'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.hours' : 'staff_settings.toast.unchanged')]);

        return back();
    }
}
