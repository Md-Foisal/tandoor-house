<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\IngredientRequest;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Support\ActivityRecorder;
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
            'summary' => fn () => $this->summary(InventoryData::seesCosts($request->user())),
            'ingredients' => Inertia::defer(fn () => $this->rows($search, $low, $supplierId, InventoryData::seesCosts($request->user()))),
        ]);
    }

    public function store(IngredientRequest $request, ActivityRecorder $activity): RedirectResponse
    {
        $ingredient = Ingredient::create([...$request->validated(), 'stock' => 0]);

        $activity->record('inventory.ingredient_created', __('staff_inventory.log.ingredient_created', [
            'user' => $request->user()->name, 'ingredient' => $ingredient->name,
        ]), $ingredient, by: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.ingredient_created', ['name' => $ingredient->name])]);

        return back();
    }

    public function update(IngredientRequest $request, Ingredient $ingredient, ActivityRecorder $activity): RedirectResponse
    {
        $ingredient->update($request->validated());

        $activity->record('inventory.ingredient_updated', __('staff_inventory.log.ingredient_updated', [
            'user' => $request->user()->name, 'ingredient' => $ingredient->name,
        ]), $ingredient, ['changes' => $ingredient->getChanges()], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.ingredient_saved', ['name' => $ingredient->name])]);

        return back();
    }

    /**
     * @return array{count: int, low: int, value: int}
     */
    private function summary(bool $costs): array
    {
        $totals = Ingredient::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when stock <= low_threshold then 1 else 0 end), 0) as low')
            ->when($costs, fn ($query) => $query->selectRaw('coalesce(sum(round(stock * cost_per_thousand / 1000.0)), 0) as value'))
            ->toBase()
            ->first();

        return [
            'count' => (int) ($totals->total ?? 0),
            'low' => (int) ($totals->low ?? 0),
            ...($costs ? ['value' => (int) ($totals->value ?? 0)] : []),
        ];
    }

    /**
     * Low items first, then by name.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $search, bool $low, ?int $supplierId, bool $costs): array
    {
        return InventoryData::ingredientsWithLastMovement()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($low, fn ($query) => $query->whereColumn('stock', '<=', 'low_threshold'))
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderByRaw('case when ingredients.stock <= ingredients.low_threshold then 0 else 1 end')
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $i) => InventoryData::ingredient($i, $costs))
            ->all();
    }
}
