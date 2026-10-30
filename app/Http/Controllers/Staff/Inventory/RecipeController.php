<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\RecipeCosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\RecipeRequest;
use App\Models\MenuItem;
use App\Models\RecipeLine;
use App\Policies\InventoryPolicy;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request, InventoryPolicy $policy): Response
    {
        abort_unless($policy->manage($request->user()), 403);

        $search = Str::of((string) $request->query('q'))->trim()->limit(60, '')->toString();
        $flagged = $request->boolean('flagged');

        return Inertia::render('staff/inventory/recipes', [
            'filters' => ['q' => $search, 'flagged' => $flagged],
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'target' => RecipeCosting::TARGET_MARGIN,
            'ingredients' => fn () => InventoryData::options(),
            'dishes' => Inertia::defer(fn () => $this->dishes($search, $flagged)),
        ]);
    }

    public function update(RecipeRequest $request, MenuItem $menuItem, ActivityRecorder $activity): RedirectResponse
    {
        /** @var list<array{ingredient_id: int, quantity: int}> $lines */
        $lines = array_values($request->validated('lines'));

        DB::transaction(function () use ($menuItem, $lines) {
            $menuItem->recipeLines()->delete();

            foreach ($lines as $line) {
                $menuItem->recipeLines()->create(['ingredient_id' => $line['ingredient_id'], 'quantity' => $line['quantity']]);
            }
        });

        $activity->record('inventory.recipe_updated', __('staff_inventory.log.recipe_updated', [
            'user' => $request->user()->name, 'dish' => $menuItem->name,
        ]), $menuItem, ['lines' => $lines], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.recipe_saved', ['name' => $menuItem->name])]);

        return back();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dishes(string $search, bool $flagged): array
    {
        return MenuItem::query()
            ->with(['category:id,name', 'recipeLines.ingredient'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get()
            ->map(function (MenuItem $item) {
                $cost = RecipeCosting::cost($item->recipeLines);
                $net = RecipeCosting::netPrice($item->price, $item->vat_rate);
                $has = $item->recipeLines->isNotEmpty();
                $margin = $has ? RecipeCosting::margin($net, $cost) : null;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category->name,
                    'price' => $item->price,
                    'net_price' => $net,
                    'cost' => $has ? $cost : null,
                    'margin' => $margin,
                    'flagged' => $margin !== null && $margin < RecipeCosting::TARGET_MARGIN,
                    'lines' => $item->recipeLines->sortBy(fn (RecipeLine $l) => $l->ingredient->name)->map(fn (RecipeLine $l) => [
                        'ingredient_id' => $l->ingredient_id,
                        'name' => $l->ingredient->name,
                        'unit' => $l->ingredient->unit,
                        'quantity' => $l->quantity,
                        'cost' => (int) round($l->quantity * $l->ingredient->cost_per_thousand / 1000),
                    ])->values()->all(),
                ];
            })
            ->sortBy([['flagged', 'desc'], ['name', 'asc']])
            ->filter(fn (array $dish) => ! $flagged || $dish['flagged'])
            ->values()
            ->all();
    }
}
