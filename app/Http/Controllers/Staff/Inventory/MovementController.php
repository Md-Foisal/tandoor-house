<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    private const PER_PAGE = 40;

    public function index(Request $request): Response
    {
        $type = StockMovementType::tryFrom((string) $request->query('type'));
        $ingredient = is_numeric($request->query('ingredient')) ? (int) $request->query('ingredient') : null;
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        return Inertia::render('staff/inventory/movements', [
            'filters' => [
                'type' => $type?->value,
                'ingredient' => $ingredient,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'ingredients' => fn () => InventoryData::options(InventoryData::seesCosts($request->user())),
            'movements' => Inertia::defer(function () use ($type, $ingredient, $from, $to, $request) {
                $page = StockMovement::query()
                    ->with(['ingredient:id,name,unit,cost_per_thousand', 'user:id,name', 'order:id,number'])
                    ->when($type, fn ($query) => $query->where('type', $type))
                    ->when($ingredient, fn ($query) => $query->where('ingredient_id', $ingredient))
                    ->when($from, fn ($query) => $query->where('created_at', '>=', $from->startOfDay()))
                    ->when($to, fn ($query) => $query->where('created_at', '<=', $to->endOfDay()))
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->paginate(self::PER_PAGE)->withQueryString();

                return [
                    'data' => $page->getCollection()->map(fn (StockMovement $m) => InventoryData::movement($m, InventoryData::seesCosts($request->user())))->all(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                ];
            }),
        ]);
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
