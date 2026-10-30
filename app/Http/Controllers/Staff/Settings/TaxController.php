<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\TaxRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TaxController extends Controller
{
    public function update(TaxRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $presets = array_values(array_map('intval', $data['tip_presets']));
        sort($presets);

        $changed = SettingsChange::save($request->user(), 'tax', [
            'vat_rate' => (int) $data['vat_rate'],
            'service_charge_rate' => (int) $data['service_charge_rate'],
            'tip_presets' => $presets,
            'reservation.deposit_per_head' => (int) $data['deposit_per_head'],
            'reservation.deposit_from_party' => (int) $data['deposit_from_party'],
        ], __('staff_settings.log.tax'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.tax' : 'staff_settings.toast.unchanged')]);

        return back();
    }
}
