<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\HygieneRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class HygieneController extends Controller
{
    public function update(HygieneRequest $request): RedirectResponse
    {
        $changed = SettingsChange::save($request->user(), 'hygiene', [
            'hygiene_rating' => $request->rating(),
            'opened_year' => (int) $request->validated('opened_year'),
        ], __('staff_settings.log.hygiene'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.hygiene' : 'staff_settings.toast.unchanged')]);

        return back();
    }
}
