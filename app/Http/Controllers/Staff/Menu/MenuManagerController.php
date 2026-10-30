<?php

namespace App\Http\Controllers\Staff\Menu;

use App\Enums\ServicePeriod;
use App\Enums\Station;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuManagerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => $request->integer('category') ?: null,
            'availability' => in_array($request->query('availability'), ['available', 'sold_out'], true) ? $request->query('availability') : null,
            'diet' => in_array($request->query('diet'), ['vegetarian', 'vegan'], true) ? $request->query('diet') : null,
            'station' => Station::tryFrom((string) $request->query('station', ''))?->value,
        ];

        $items = MenuItem::query()
            ->withCount('variants')
            ->withMin('variants', 'price')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%'))
            ->when($filters['category'], fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['availability'], fn ($q, $value) => $q->where('is_available', $value === 'available'))
            ->when($filters['diet'] === 'vegetarian', fn ($q) => $q->where('is_vegetarian', true))
            ->when($filters['diet'] === 'vegan', fn ($q) => $q->where('is_vegan', true))
            ->when($filters['station'], fn ($q, $station) => $q->where('station', $station))
            ->orderBy('position')
            ->get();

        return Inertia::render('staff/menu/index', [
            'filters' => $filters,
            'categories' => Category::query()->withCount('items')->orderBy('position')->orderBy('id')->get()->map(MenuData::category(...))->values(),
            'items' => $items->map(MenuData::row(...))->values(),
            'total' => MenuItem::query()->count(),
            'stations' => collect(Station::cases())->map(fn (Station $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'periods' => collect(ServicePeriod::cases())->map(fn (ServicePeriod $p) => $p->value)->values(),
        ]);
    }
}
