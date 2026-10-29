<?php

namespace App\Http\Controllers\Staff\Menu;

use App\Actions\Menu\SetItemFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Menu\ItemFlagRequest;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ItemFlagController extends Controller
{
    public function availability(ItemFlagRequest $request, MenuItem $item, SetItemFlag $action): RedirectResponse
    {
        return $this->set($request, $item, 'is_available', $action);
    }

    public function special(ItemFlagRequest $request, MenuItem $item, SetItemFlag $action): RedirectResponse
    {
        return $this->set($request, $item, 'is_special', $action);
    }

    private function set(ItemFlagRequest $request, MenuItem $item, string $flag, SetItemFlag $action): RedirectResponse
    {
        $value = $request->boolean('value');
        $action->handle($item, $flag, $value, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __("staff_menu.flash.{$flag}_".($value ? 'on' : 'off'), ['name' => $item->name])]);

        return back();
    }
}
