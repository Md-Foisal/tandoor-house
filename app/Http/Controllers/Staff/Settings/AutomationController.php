<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\AutomationRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AutomationController extends Controller
{
    public function update(AutomationRequest $request, Settings $settings): RedirectResponse
    {
        $before = SettingsController::automations($settings);
        $after = array_map(fn ($value) => (bool) $value, $request->validated());
        $flipped = array_keys(array_filter($after, fn (bool $on, string $key) => $on !== $before[$key], ARRAY_FILTER_USE_BOTH));

        $changed = SettingsChange::save($request->user(), 'automations', ['automations' => $after], 'Changed automations: '.implode(', ', $flipped));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.automations' : 'staff_settings.toast.unchanged')]);

        return back();
    }
}
