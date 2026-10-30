<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\HolidaysRequest;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BankHolidayController extends Controller
{
    public const NEXT_YEAR = 2027;

    /** England and Wales bank holidays, with the Christmas substitutes. */
    public const NEXT_YEAR_DATES = [
        '2027-01-01', '2027-03-26', '2027-03-29', '2027-05-03', '2027-05-31',
        '2027-08-30', '2027-12-27', '2027-12-28',
    ];

    public function update(HolidaysRequest $request): RedirectResponse
    {
        $changed = SettingsChange::save($request->user(), 'holidays', ['bank_holidays' => $request->dates()], __('staff_settings.log.holidays'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.holidays' : 'staff_settings.toast.unchanged')]);

        return back();
    }

    public function addNextYear(Request $request, Settings $settings): RedirectResponse
    {
        Gate::authorize('update', Setting::class);

        $merged = array_values(array_unique([...array_map('strval', (array) $settings->get('bank_holidays', [])), ...self::NEXT_YEAR_DATES]));
        sort($merged);

        SettingsChange::save($request->user(), 'holidays', ['bank_holidays' => $merged], __('staff_settings.log.holidays_year', ['year' => self::NEXT_YEAR]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_settings.toast.holidays_year', ['year' => self::NEXT_YEAR])]);

        return back();
    }
}
