<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LineStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\Station;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MenuController;
use App\Http\Requests\Staff\Kitchen\BumpOrderRequest;
use App\Http\Requests\Staff\Kitchen\IndexRequest;
use App\Http\Requests\Staff\Kitchen\SoldOutRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Support\ActivityRecorder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    private const RECENT_MINUTES = 10;

    private const ALLERGY_WORDS = '/allerg|\bnuts?\b|peanut|gluten|coeliac|celiac|dairy|lactose|intoleran|sesame|shellfish/i';

    public function __construct(
        private readonly OrderStateMachine $orders,
        private readonly ActivityRecorder $activity,
    ) {}

    public function index(IndexRequest $request): Response
    {
        $station = $request->station();
        $live = $this->liveOrders();

        $counts = ['all' => 0];
        foreach (Station::cases() as $case) {
            $counts[$case->value] = 0;
        }

        foreach ($live as $order) {
            $counts['all']++;

            foreach ($order->lines->where('status', '!=', LineStatus::Ready)->pluck('station')->unique() as $lineStation) {
                $counts[$lineStation->value]++;
            }
        }

        $tickets = $live
            ->map(fn (Order $order) => $this->ticket($order, $station))
            ->filter()
            ->values();

        return Inertia::render('staff/kitchen/index', [
            'station' => $station,
            'stations' => [
                ['value' => 'all', 'label' => __('staff_kitchen.stations.all'), 'count' => $counts['all']],
                ...array_map(fn (Station $case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                    'count' => $counts[$case->value],
                ], Station::cases()),
            ],
            'tickets' => $tickets,
            'all_day' => $this->allDay($live, $station),
            'recent' => $this->recent($station),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function bumpLine(OrderLine $line): RedirectResponse
    {
        Gate::authorize('staff.kitchen.cook');

        $this->orders->bump($line, $this->user());

        return back();
    }

    public function recallLine(OrderLine $line): RedirectResponse
    {
        Gate::authorize('staff.kitchen.cook');

        $this->orders->recall($line, $this->user());

        return back();
    }

    public function bumpOrder(BumpOrderRequest $request, Order $order): RedirectResponse
    {
        $station = $request->validated('station');
        $user = $this->user();

        DB::transaction(function () use ($order, $station, $user) {
            $order->load(['lines' => fn ($query) => $query
                ->where('status', '!=', LineStatus::Ready)
                ->when($station !== 'all', fn ($query) => $query->where('station', $station))
                ->orderBy('id')]);

            foreach ($order->lines as $line) {
                $line->setRelation('order', $order);
                $this->orders->bump($line, $user);
            }
        });

        return back();
    }

    public function soldOut(SoldOutRequest $request, MenuItem $item): RedirectResponse
    {
        if ($item->is_available) {
            $item->forceFill(['is_available' => false])->save();
            Cache::forget(MenuController::CACHE_KEY);

            $this->activity->record(
                'menu.sold_out',
                __('staff_kitchen.log.sold_out', ['dish' => $item->name, 'name' => $this->user()->name]),
                $item,
                ['source' => 'kitchen'],
                $this->user(),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_kitchen.sold_out_done', ['dish' => $item->name])]);

        return back();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    /**
     * @return Collection<int, Order>
     */
    private function liveOrders(): Collection
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Accepted, OrderStatus::Cooking])
            ->with(['lines' => fn ($query) => $query->orderBy('id'), 'lines.menuItem:id,prep_minutes,is_available', 'diningTable:id,label'])
            ->orderBy('accepted_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ticket(Order $order, string $station): ?array
    {
        $lines = $order->lines
            ->when($station !== 'all', fn ($lines) => $lines->filter(fn (OrderLine $line) => $line->station->value === $station))
            ->values();

        if ($lines->isEmpty() || ($station !== 'all' && $lines->every(fn (OrderLine $line) => $line->status === LineStatus::Ready))) {
            return null;
        }

        $prep = (int) ($order->lines->max(fn (OrderLine $line) => $line->menuItem->prep_minutes ?? 0) ?: 15);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'channel' => $order->channel->value,
            'table' => $order->channel === OrderChannel::DineIn ? $order->diningTable?->label : null,
            'pickup_time' => $order->channel === OrderChannel::Pickup ? $order->pickup_at?->format('H:i') : null,
            'accepted_at' => $order->accepted_at?->toIso8601String(),
            'warn_after' => (int) max(1, round($prep * 0.6)),
            'late_after' => $prep,
            'notes' => $order->notes,
            'lines' => $lines->map(fn (OrderLine $line) => [
                'id' => $line->id,
                'quantity' => $line->quantity,
                'name' => $line->name,
                'variant' => $line->variant_name,
                'modifiers' => array_map(fn (array $modifier) => $modifier['name'], $line->modifiers),
                'notes' => $line->notes,
                'allergy' => $line->notes !== null && preg_match(self::ALLERGY_WORDS, $line->notes) === 1,
                'station' => $line->station->value,
                'done' => $line->status === LineStatus::Ready,
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $live
     * @return array<int, array{menu_item_id: int|null, name: string, quantity: int, available: bool}>
     */
    private function allDay(Collection $live, string $station): array
    {
        return $live
            ->flatMap(fn (Order $order) => $order->lines)
            ->filter(fn (OrderLine $line) => $line->status !== LineStatus::Ready && ($station === 'all' || $line->station->value === $station))
            ->groupBy(fn (OrderLine $line) => $line->menu_item_id ?? $line->name)
            ->map(fn ($group) => [
                'menu_item_id' => $group->first()->menu_item_id,
                'name' => $group->first()->name,
                'quantity' => (int) $group->sum('quantity'),
                'available' => $group->first()->menuItem->is_available ?? true,
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, number: string, name: string, quantity: int, bumped_at: string|null}>
     */
    private function recent(string $station): array
    {
        return OrderLine::query()
            ->where('status', LineStatus::Ready)
            ->where('bumped_at', '>=', now()->subMinutes(self::RECENT_MINUTES))
            ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready]))
            ->when($station !== 'all', fn ($query) => $query->where('station', $station))
            ->with('order:id,number')
            ->orderByDesc('bumped_at')
            ->limit(12)
            ->get()
            ->map(fn (OrderLine $line) => [
                'id' => $line->id,
                'number' => $line->order->number,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'bumped_at' => $line->bumped_at?->toIso8601String(),
            ])
            ->all();
    }
}
