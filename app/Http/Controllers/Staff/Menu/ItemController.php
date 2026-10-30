<?php

namespace App\Http\Controllers\Staff\Menu;

use App\Actions\Menu\SaveMenuItem;
use App\Enums\Role;
use App\Enums\ServicePeriod;
use App\Enums\Station;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Menu\SaveItemRequest;
use App\Models\Category;
use App\Models\MenuItem;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function create(Settings $settings): Response
    {
        return $this->form(null, $settings);
    }

    public function edit(MenuItem $item, Settings $settings): Response
    {
        $item->load(['variants', 'modifierGroups.modifiers', 'priceChanges.user']);

        return $this->form($item, $settings);
    }

    public function store(SaveItemRequest $request, SaveMenuItem $action): RedirectResponse
    {
        $item = $action->handle($this->data($request), $request->user(), null, $request->file('photo'), $request->boolean('remove_photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_menu.flash.item_created', ['name' => $item->name])]);

        return redirect()->route('staff.menu.items.edit', $item);
    }

    public function update(SaveItemRequest $request, MenuItem $item, SaveMenuItem $action): RedirectResponse
    {
        $action->handle($this->data($request), $request->user(), $item, $request->file('photo'), $request->boolean('remove_photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_menu.flash.item_updated', ['name' => $item->name])]);

        return redirect()->route('staff.menu.items.edit', $item);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(SaveItemRequest $request): array
    {
        return collect($request->validated())->except(['photo', 'remove_photo', 'allergens_checked'])->all();
    }

    private function form(?MenuItem $item, Settings $settings): Response
    {
        return Inertia::render('staff/menu/edit', [
            'item' => $item === null ? null : MenuData::editor($item),
            'history' => $item === null ? [] : MenuData::history($item),
            'categories' => Category::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
            'stations' => collect(Station::cases())->map(fn (Station $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'periods' => collect(ServicePeriod::cases())->map(fn (ServicePeriod $p) => $p->value)->values(),
            'default_category' => request()->integer('category') ?: null,
            'default_vat' => (int) $settings->get('vat_rate', 2000),
            'can_change_vat' => request()->user()?->role === Role::Owner,
        ]);
    }
}
