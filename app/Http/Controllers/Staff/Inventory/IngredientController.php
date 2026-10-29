<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\InventoryActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\IngredientRequest;
use App\Models\Ingredient;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = Str::of((string) $request->query('q'))->trim()->limit(60, '')->toString();
        $low = $request->boolean('low');
        $supplier = $request->query('supplier');
        $supplierId = is_numeric($supplier) ? (int) $supplier : null;

        return Inertia::render('staff/inventory/index', [
            'filters' => ['q' => $search, 'low' => $low, 'supplier' => $supplierId],
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'suppliers' => fn () => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'summary' => fn () => $this->summary(),
            'ingredients' => Inertia::defer(fn () => $this->rows($search, $low, $supplierId)),
        ]);
    }

    public function store(IngredientRequest $request): RedirectResponse
    {
        $ingredient = Ingredient::create([...$request->validated(), 'stock' => 0]);

        InventoryActivity::record('inventory.ingredient_created', $ingredient, $request->user(), __('staff_inventory.log.ingredient_created', [
            'user' => $request->user()->name, 'ingredient' => $ingredient->name,
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.ingredient_created', ['name' => $ingredient->name])]);

        return back();
    }

    public function update(IngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $ingredient->update($request->validated());

        InventoryActivity::record('inventory.ingredient_updated', $ingredient, $request->user(), __('staff_inventory.log.ingredient_updated', [
            'user' => $request->user()->name, 'ingredient' => $ingredient->name,
        ]), ['changes' => $ingredient->getChanges()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.ingredient_saved', ['name' => $ingredient->name])]);

        return back();
    }

    /**
     * @return array{count: int, low: int, value: int}
     */
    private function summary(): array
    {
        $all = Ingredient::query()->get(['stock', 'low_threshold', 'cost_per_thousand']);

        return [
            'count' => $all->count(),
            'low' => $all->filter(fn (Ingredient $i) => $i->stock <= $i->low_threshold)->count(),
            'value' => $all->sum(fn (Ingredient $i) => InventoryData::value($i->stock, $i->cost_per_thousand)),
        ];
    }

    /**
     * Low items first, then by name.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $search, bool $low, ?int $supplierId): array
    {
        return InventoryData::ingredientsWithLastMovement()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($low, fn ($query) => $query->whereColumn('stock', '<=', 'low_threshold'))
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $i) => InventoryData::ingredient($i))
            ->sortBy(fn (array $row) => $row['is_low'] ? 0 : 1)
            ->values()
            ->all();
    }
}
