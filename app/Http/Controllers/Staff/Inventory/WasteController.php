<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\StockLedger;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\WasteRequest;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WasteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('staff/inventory/waste', [
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'ingredients' => fn () => InventoryData::options(),
            'summary' => fn () => $this->summary(),
            'entries' => Inertia::defer(fn () => StockMovement::query()
                ->where('type', StockMovementType::Waste)
                ->with(['ingredient:id,name,unit,cost_per_thousand', 'user:id,name', 'order:id,number'])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(60)->get()
                ->map(fn (StockMovement $m) => InventoryData::movement($m))->all()),
        ]);
    }

    public function store(WasteRequest $request, StockLedger $ledger): RedirectResponse
    {
        $ingredient = Ingredient::query()->findOrFail($request->integer('ingredient_id'));
        $note = $request->string('note')->trim()->toString();
        $label = __('staff_inventory.waste.reasons.'.$request->string('reason')->toString());

        $ledger->waste($ingredient, $request->integer('quantity'), $note !== '' ? "{$label}: {$note}" : $label, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.waste', ['name' => $ingredient->name])]);

        return back();
    }

    /**
     * @return array{cost: int, entries: int, top: array<int, array{name: string, cost: int}>, since: string}
     */
    private function summary(): array
    {
        $since = Carbon::now()->startOfWeek();

        $week = StockMovement::query()
            ->where('type', StockMovementType::Waste)
            ->where('created_at', '>=', $since)
            ->with('ingredient:id,name,cost_per_thousand')
            ->get();

        $byIngredient = $week->groupBy('ingredient_id')->map(fn ($rows) => [
            'name' => $rows->first()->ingredient->name,
            'cost' => $rows->sum(fn (StockMovement $m) => InventoryData::value(abs($m->quantity), $m->ingredient->cost_per_thousand)),
        ])->sortByDesc('cost')->values();

        return [
            'cost' => $byIngredient->sum('cost'),
            'entries' => $week->count(),
            'top' => $byIngredient->take(3)->all(),
            'since' => $since->toIso8601String(),
        ];
    }
}
