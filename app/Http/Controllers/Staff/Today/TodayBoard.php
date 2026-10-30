<?php

namespace App\Http\Controllers\Staff\Today;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TableRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The numbers behind the Today screen, one method per role view. Every
 * query is bounded to today or to what is live right now.
 */
class TodayBoard
{
    public const SLOW_ACCEPT_MINUTES = 5;

    public const DEFAULT_PREP_MINUTES = 15;

    private const FIRST_HOUR = 11;

    private const LAST_HOUR = 23;

    /** Orders that count towards sales: anything not turned away. */
    private const NOT_SALES = [OrderStatus::Rejected, OrderStatus::Cancelled];

    public static function viewFor(Role $role): string
    {
        return match ($role) {
            Role::Manager, Role::Owner => 'management',
            Role::Kitchen => 'kitchen',
            Role::Rider => 'rider',
            default => 'front',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return match (self::viewFor($user->role)) {
            'management' => $this->management(),
            'kitchen' => $this->kitchen(),
            'rider' => $this->rider($user),
            default => $this->front(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function management(): array
    {
        $now = now();
        $start = $now->copy()->startOfDay();

        $sales = Order::query()->where('placed_at', '>=', $start)->whereNotIn('status', self::NOT_SALES);

        $channels = (clone $sales)->selectRaw('channel, count(*) as total')->groupBy('channel')->pluck('total', 'channel');

        return [
            'tiles' => [
                'sales' => (int) (clone $sales)->sum('total'),
                'orders_by_channel' => collect(OrderChannel::cases())
                    ->mapWithKeys(fn (OrderChannel $channel) => [$channel->value => (int) ($channels[$channel->value] ?? 0)])
                    ->all(),
                'covers_tonight' => (int) $this->tonight()->whereIn('status', $this->booked())->sum('party_size'),
                'average_prep_minutes' => $this->averagePrep($start),
                'late_tickets' => $this->lateTickets()->count(),
                'low_stock' => $this->lowStock()->count(),
                'staff_on_shift' => Attendance::query()->whereNull('clocked_out_at')->count(),
            ],
            'hourly' => $this->hourly($now),
            'attention' => [
                'slow_orders' => $this->waiting()->filter(fn (Order $order) => $order->placed_at->diffInMinutes($now, true) >= self::SLOW_ACCEPT_MINUTES)->values()->map(fn (Order $order) => $this->order($order))->all(),
                'table_requests' => $this->tableRequests(),
                'unpaid_deposits' => $this->tonight()->where('status', ReservationStatus::PendingDeposit)->orderBy('starts_at')->get()
                    ->map(fn (Reservation $reservation) => [
                        'id' => $reservation->id,
                        'reference' => $reservation->reference,
                        'name' => $reservation->name,
                        'party_size' => $reservation->party_size,
                        'starts_at' => $reservation->starts_at->toIso8601String(),
                        'deposit' => $reservation->deposit,
                    ])->all(),
                'low_stock' => $this->lowStockList(),
                'unpublished_reviews' => Review::query()->where('is_published', false)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function front(): array
    {
        $now = now();

        return [
            'waiting' => $this->waiting()->map(fn (Order $order) => $this->order($order))->all(),
            'ready' => Order::query()
                ->with('diningTable')
                ->where('status', OrderStatus::Ready)
                ->whereIn('channel', [OrderChannel::Pickup, OrderChannel::DineIn])
                ->orderBy('ready_at')
                ->get()
                ->map(fn (Order $order) => $this->order($order, $order->ready_at))
                ->all(),
            'table_requests' => $this->tableRequests(),
            'bookings' => Reservation::query()
                ->with('diningTable')
                ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit])
                ->whereBetween('starts_at', [$now, $now->copy()->addHours(2)])
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Reservation $reservation) => [
                    'id' => $reservation->id,
                    'name' => $reservation->name,
                    'party_size' => $reservation->party_size,
                    'starts_at' => $reservation->starts_at->toIso8601String(),
                    'table' => $reservation->diningTable?->label,
                    'deposit_pending' => $reservation->status === ReservationStatus::PendingDeposit,
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kitchen(): array
    {
        $late = $this->lateTickets();

        return [
            'in_kitchen' => Order::query()->whereIn('status', [OrderStatus::Accepted, OrderStatus::Cooking])->count(),
            'late' => $late->count(),
            'late_tickets' => $late->map(fn (Order $order) => $this->order($order, $order->accepted_at))->values()->all(),
            'low_stock' => $this->lowStockList(),
            'low_stock_count' => $this->lowStock()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rider(User $user): array
    {
        $mine = Order::query()->where('rider_id', $user->id)->where('channel', OrderChannel::Delivery);
        $today = now()->startOfDay();

        return [
            'out_now' => (clone $mine)->with('deliveryZone')->where('status', OrderStatus::OutForDelivery)->orderBy('placed_at')->get()
                ->map(fn (Order $order) => $this->delivery($order))->all(),
            'to_collect' => (clone $mine)->with('deliveryZone')->where('status', OrderStatus::Ready)->orderBy('ready_at')->get()
                ->map(fn (Order $order) => $this->delivery($order))->all(),
            'cash_collected' => (int) (clone $mine)
                ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Completed])
                ->where('updated_at', '>=', $today)
                ->sum('cash_collected'),
            'delivered_today' => (clone $mine)
                ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Completed])
                ->where('updated_at', '>=', $today)
                ->count(),
        ];
    }

    /**
     * Sales by hour for today and the same weekday last week, in pence.
     *
     * @return array<int, array{hour: int, today: int|null, last_week: int}>
     */
    private function hourly(CarbonInterface $now): array
    {
        $start = $now->copy()->startOfDay();
        $lastWeek = $start->copy()->subWeek();

        $bucket = function ($from) {
            return Order::query()
                ->whereBetween('placed_at', [$from, $from->copy()->endOfDay()])
                ->whereNotIn('status', self::NOT_SALES)
                ->get(['placed_at', 'total'])
                ->groupBy(fn (Order $order) => $order->placed_at->hour)
                ->map(fn (Collection $orders) => (int) $orders->sum('total'));
        };

        $today = $bucket($start);
        $before = $bucket($lastWeek);

        return collect(range(self::FIRST_HOUR, self::LAST_HOUR))
            ->map(fn (int $hour) => [
                'hour' => $hour,
                'today' => $hour > $now->hour ? null : (int) ($today[$hour] ?? 0),
                'last_week' => (int) ($before[$hour] ?? 0),
            ])->values()->all();
    }

    private function averagePrep(CarbonInterface $start): ?int
    {
        $minutes = Order::query()
            ->where('accepted_at', '>=', $start)
            ->whereNotNull('ready_at')
            ->get(['accepted_at', 'ready_at'])
            ->map(fn (Order $order) => $order->accepted_at?->diffInSeconds($order->ready_at, true) / 60);

        return $minutes->isEmpty() ? null : (int) round($minutes->avg());
    }

    /**
     * Orders in the kitchen for longer than their slowest dish should take.
     *
     * @return Collection<int, Order>
     */
    private function lateTickets(): Collection
    {
        return Order::query()
            ->with(['diningTable', 'lines.menuItem'])
            ->whereIn('status', [OrderStatus::Accepted, OrderStatus::Cooking])
            ->whereNotNull('accepted_at')
            ->orderBy('accepted_at')
            ->get()
            ->filter(function (Order $order) {
                $prep = (int) ($order->lines->max(fn ($line) => $line->menuItem->prep_minutes ?? 0) ?: self::DEFAULT_PREP_MINUTES);

                return $order->accepted_at !== null && $order->accepted_at->diffInMinutes(now(), true) > $prep;
            });
    }

    /**
     * @return Collection<int, Order>
     */
    private function waiting(): Collection
    {
        return Order::query()->releasedToStaff()->with('diningTable')->where('status', OrderStatus::Placed)->orderBy('placed_at')->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tableRequests(): array
    {
        return TableRequest::query()
            ->with('diningTable')
            ->whereNull('handled_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TableRequest $request) => [
                'id' => $request->id,
                'table' => $request->diningTable->label,
                'type' => $request->type->value,
                'since' => $request->created_at?->toIso8601String(),
            ])->values()->all();
    }

    /**
     * @return Builder<Reservation>
     */
    private function tonight()
    {
        $today = now()->startOfDay();

        return Reservation::query()->whereBetween('starts_at', [$today->copy()->setTime(17, 0), $today->copy()->endOfDay()]);
    }

    /**
     * @return list<ReservationStatus>
     */
    private function booked(): array
    {
        return [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit, ReservationStatus::Seated];
    }

    /**
     * @return Builder<Ingredient>
     */
    private function lowStock()
    {
        return Ingredient::query()->whereColumn('stock', '<=', 'low_threshold');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStockList(): array
    {
        return $this->lowStock()->orderByRaw('stock * 1.0 / nullif(low_threshold, 0)')->limit(6)->get()
            ->map(fn (Ingredient $ingredient) => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'stock' => $ingredient->stock,
                'unit' => $ingredient->unit,
                'low_threshold' => $ingredient->low_threshold,
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function order(Order $order, ?CarbonInterface $since = null): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'channel' => $order->channel->value,
            'status' => $order->status->value,
            'table' => $order->channel === OrderChannel::DineIn ? $order->diningTable?->label : null,
            'name' => $order->contact['name'] ?? null,
            'total' => $order->total,
            'since' => ($since ?? $order->placed_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function delivery(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'name' => $order->contact['name'] ?? null,
            'postcode' => $order->postcode,
            'total' => $order->total,
            'cash_due' => $order->payment_method->value === 'cash' ? $order->total : 0,
            'since' => ($order->ready_at ?? $order->placed_at)->toIso8601String(),
        ];
    }
}
