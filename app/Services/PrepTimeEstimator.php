<?php

namespace App\Services;

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Says when an order will be ready: its slowest dish, using how long that
 * dish has really taken lately, plus a wait for a busy station.
 */
class PrepTimeEstimator
{
    private const HISTORY_DAYS = 30;

    private const MIN_SAMPLES = 3;

    private const CACHE_MINUTES = 5;

    /** Each this many dishes already cooking on a station adds a minute. */
    private const LINES_PER_EXTRA_MINUTE = 2;

    private const MAX_QUEUE_MINUTES = 30;

    public function estimate(Order $order, ?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? CarbonImmutable::now())->addMinutes($this->minutes($order));
    }

    /**
     * Minutes still needed: the slowest unfinished line plus the station
     * queue, and the zone's travel time for delivery.
     */
    public function minutes(Order $order): int
    {
        $lines = $order->relationLoaded('lines') ? $order->lines : $order->lines()->get();
        $open = $lines->filter(fn (OrderLine $line) => $line->status !== LineStatus::Ready)->values();

        $medians = $this->medians(array_values(array_map(intval(...), $open->pluck('menu_item_id')->filter()->unique()->all())));
        $loads = $this->stationLoads($order);

        $kitchen = $open->map(function (OrderLine $line) use ($medians, $loads) {
            $base = $line->menu_item_id !== null ? ($medians[$line->menu_item_id] ?? null) : null;

            return ($base ?? 10) + min(self::MAX_QUEUE_MINUTES, intdiv($loads[$line->station->value] ?? 0, self::LINES_PER_EXTRA_MINUTE));
        })->max() ?? 0;

        $travel = $order->delivery_zone_id !== null
            ? (int) ($order->relationLoaded('deliveryZone') ? $order->deliveryZone : $order->deliveryZone()->first())?->eta_minutes
            : 0;

        return (int) $kitchen + $travel;
    }

    public function refresh(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::Accepted, OrderStatus::Cooking], true)) {
            return;
        }

        $order->forceFill(['estimated_ready_at' => $this->estimate($order)])->save();
    }

    /**
     * Median prep minutes per menu item over the last 30 days, cached for a
     * few minutes. Items with too little history fall back to the menu's own figure.
     *
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    private function medians(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $keys = [];
        foreach ($itemIds as $id) {
            $keys[$id] = "prep-median:{$id}";
        }

        $cached = Cache::many(array_values($keys));
        $found = [];
        $missing = [];

        foreach ($keys as $id => $key) {
            if (($cached[$key] ?? null) !== null) {
                $found[$id] = (int) $cached[$key];
            } else {
                $missing[] = $id;
            }
        }

        if ($missing === []) {
            return $found;
        }

        $samples = OrderLine::query()
            ->whereIn('menu_item_id', $missing)
            ->whereNotNull('started_at')
            ->whereNotNull('bumped_at')
            ->where('bumped_at', '>=', now()->subDays(self::HISTORY_DAYS))
            ->get(['menu_item_id', 'started_at', 'bumped_at'])
            ->groupBy('menu_item_id');

        $fallbacks = MenuItem::query()->whereIn('id', $missing)->pluck('prep_minutes', 'id');

        foreach ($missing as $id) {
            $minutes = ($samples[$id] ?? collect())
                ->map(fn (OrderLine $line) => max(1, (int) ceil($line->started_at->diffInSeconds($line->bumped_at, true) / 60)))
                ->sort()
                ->values();

            $median = $minutes->count() >= self::MIN_SAMPLES
                ? $minutes[intdiv($minutes->count(), 2)]
                : (int) ($fallbacks[$id] ?? 10);

            $found[$id] = $median;
            Cache::put($keys[$id], $median, now()->addMinutes(self::CACHE_MINUTES));
        }

        return $found;
    }

    /**
     * Dishes cooking right now on each station, not counting this order.
     *
     * @return array<string, int>
     */
    private function stationLoads(Order $order): array
    {
        return OrderLine::query()
            ->where('status', LineStatus::Cooking)
            ->where('order_id', '!=', $order->id)
            ->selectRaw('station, count(*) as total')
            ->groupBy('station')
            ->pluck('total', 'station')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
