<?php

namespace App\Reports\Sections;

use App\Models\MenuItem;
use App\Reports\OrderScope;
use App\Reports\ReportRange;
use App\Reports\ReportTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Items
{
    private const LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function data(ReportRange $range): array
    {
        $sold = $this->sold($range);
        $menu = MenuItem::query()->get(['id', 'name', 'is_available']);

        $sales = $sold->map(fn (array $row) => [
            'id' => $row['id'],
            'name' => $menu->firstWhere('id', $row['id'])->name ?? $row['name'],
            'quantity' => $row['quantity'],
            'revenue' => $row['revenue'],
        ])->values();

        // Dishes the kitchen could not sell are not "slow" if they were switched off.
        $candidates = $menu
            ->filter(fn (MenuItem $item) => $item->is_available || $sold->has($item->id))
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $sold[$item->id]['quantity'] ?? 0,
                'revenue' => $sold[$item->id]['revenue'] ?? 0,
            ])->values();

        $top = fn (string $by) => $sales->sortBy([[$by, 'desc'], ['name', 'asc']])->take(self::LIMIT)->values()->all();
        $slow = fn (string $by) => $candidates->sortBy([[$by, 'asc'], ['name', 'asc']])->take(self::LIMIT)->values()->all();

        $lists = [
            'top_quantity' => $top('quantity'),
            'top_revenue' => $top('revenue'),
            'slow_quantity' => $slow('quantity'),
            'slow_revenue' => $slow('revenue'),
        ];
        $categories = $this->categories($range);

        $itemColumns = [
            'rank' => 'int|#',
            'name' => 'text|'.__('staff_reports.columns.dish'),
            'quantity' => 'int|'.__('staff_reports.columns.sold'),
            'revenue' => 'money|'.__('staff_reports.columns.revenue'),
        ];

        $tables = [];

        foreach ($lists as $key => $rows) {
            $tables[] = ReportTable::make(
                $key,
                __("staff_reports.items.tables.{$key}"),
                $itemColumns,
                array_map(fn (array $row, int $i) => ['rank' => $i + 1, ...array_diff_key($row, ['id' => 1])], $rows, array_keys($rows)),
                note: $this->noteFor($key),
            )->toArray();
        }

        $tables[] = ReportTable::make('categories', __('staff_reports.items.tables.categories'), [
            'name' => 'text|'.__('staff_reports.columns.category'),
            'quantity' => 'int|'.__('staff_reports.columns.sold'),
            'revenue' => 'money|'.__('staff_reports.columns.revenue'),
            'share' => 'percent|'.__('staff_reports.columns.share'),
        ], $categories, note: __('staff_reports.items.revenue_note'))->toArray();

        return [
            ...array_map(fn (array $rows) => array_map(fn (array $row) => array_diff_key($row, ['id' => 1]), $rows), $lists),
            'categories' => $categories,
            'tables' => $tables,
        ];
    }

    private function noteFor(string $key): ?string
    {
        return str_starts_with($key, 'slow') ? trans()->string('staff_reports.items.slow_note') : null;
    }

    /**
     * Quantity and revenue (before discounts, VAT included) per dish, keyed by menu item id.
     * Dishes since removed from the menu are grouped by the name on the order.
     *
     * @return Collection<int, array{id: int, name: string, quantity: int, revenue: int}>
     */
    private function sold(ReportRange $range)
    {
        $rows = DB::table('order_lines')
            ->joinSub(OrderScope::orders($range)->select('orders.id'), 'scoped', 'scoped.id', '=', 'order_lines.order_id')
            ->selectRaw('order_lines.menu_item_id as menu_item_id, order_lines.name as name, sum(order_lines.quantity) as quantity, sum(order_lines.unit_price * order_lines.quantity) as revenue')
            ->groupBy('order_lines.menu_item_id', 'order_lines.name')
            ->get();

        $grouped = collect();
        $removed = -1;

        foreach ($rows as $row) {
            $id = $row->menu_item_id !== null ? (int) $row->menu_item_id : $removed--;
            $existing = $grouped[$id] ?? ['id' => $id, 'name' => (string) $row->name, 'quantity' => 0, 'revenue' => 0];
            $existing['quantity'] += (int) $row->quantity;
            $existing['revenue'] += (int) $row->revenue;
            $grouped[$id] = $existing;
        }

        return $grouped;
    }

    /**
     * @return array<int, array{name: string, quantity: int, revenue: int, share: float}>
     */
    private function categories(ReportRange $range): array
    {
        $rows = DB::table('order_lines')
            ->joinSub(OrderScope::orders($range)->select('orders.id'), 'scoped', 'scoped.id', '=', 'order_lines.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_lines.menu_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'menu_items.category_id')
            ->selectRaw('categories.id as category_id, max(categories.name) as name, sum(order_lines.quantity) as quantity, sum(order_lines.unit_price * order_lines.quantity) as revenue')
            ->groupBy('categories.id')
            ->get();

        $total = (int) $rows->sum('revenue');

        return $rows
            ->map(fn ($row) => [
                'name' => (string) ($row->name ?? __('staff_reports.items.removed')),
                'quantity' => (int) $row->quantity,
                'revenue' => (int) $row->revenue,
                'share' => $total > 0 ? round($row->revenue / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
