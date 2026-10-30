<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\ReceiptRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    public function update(ReceiptRequest $request): RedirectResponse
    {
        $footer = trim((string) $request->validated('footer'));

        $changed = SettingsChange::save($request->user(), 'receipts', ['receipt_footer' => $footer], __('staff_settings.log.receipts'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($changed ? 'staff_settings.toast.receipts' : 'staff_settings.toast.unchanged')]);

        return back();
    }
}
