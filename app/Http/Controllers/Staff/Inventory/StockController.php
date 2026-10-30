<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\StockLedger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\AdjustRequest;
use App\Http\Requests\Staff\Inventory\StockInRequest;
use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StockController extends Controller
{
    public function stockIn(StockInRequest $request, Ingredient $ingredient, StockLedger $ledger): RedirectResponse
    {
        $reason = $request->string('reason')->trim()->toString();

        $ledger->stockIn($ingredient, $request->integer('quantity'), $reason !== '' ? $reason : null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.stock_in', ['name' => $ingredient->name])]);

        return back();
    }

    public function adjust(AdjustRequest $request, Ingredient $ingredient, StockLedger $ledger): RedirectResponse
    {
        $ledger->adjust($ingredient, $request->integer('counted'), $request->string('reason')->trim()->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.adjusted', ['name' => $ingredient->name])]);

        return back();
    }
}
