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
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        return Inertia::render('staff/inventory/waste', [
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'ingredients' => fn () => InventoryData::options(),
            'summary' => fn () => $this->summary(),
            'entries' => Inertia::defer(function () {
                $page = StockMovement::query()
                    ->where('type', StockMovementType::Waste)
                    ->with(['ingredient:id,name,unit,cost_per_thousand', 'user:id,name', 'order:id,number'])
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->paginate(self::PER_PAGE)->withQueryString();

                return [
                    'data' => $page->getCollection()->map(fn (StockMovement $m) => InventoryData::movement($m))->all(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                ];
            }),
        ]);
    }

    public function store(WasteRequest $request, StockLedger $ledger): RedirectResponse
    {
        $ingredient = Ingredient::query()->whereKey($request->integer('ingredient_id'))->firstOrFail();
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

        $byIngredient = StockMovement::query()
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->where('stock_movements.type', StockMovementType::Waste)
            ->where('stock_movements.created_at', '>=', $since)
            ->groupBy('ingredients.id', 'ingredients.name')
            ->selectRaw('ingredients.name as name, count(*) as entries, sum(round(abs(stock_movements.quantity) * ingredients.cost_per_thousand / 1000.0)) as cost')
            ->toBase()
            ->get()
            ->map(fn (object $row) => ['name' => (string) $row->name, 'entries' => (int) $row->entries, 'cost' => (int) $row->cost])
            ->sortByDesc('cost')
            ->values();

        return [
            'cost' => (int) $byIngredient->sum('cost'),
            'entries' => (int) $byIngredient->sum('entries'),
            'top' => $byIngredient->take(3)->map(fn (array $row) => ['name' => $row['name'], 'cost' => $row['cost']])->all(),
            'since' => $since->toIso8601String(),
        ];
    }
}
