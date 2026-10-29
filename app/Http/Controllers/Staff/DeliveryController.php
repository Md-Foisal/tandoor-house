<?php

namespace App\Http\Controllers\Staff;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Policies\DeliveryPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    /** Roughly how many items fit in one carrier bag. */
    private const ITEMS_PER_BAG = 6;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $policy = new DeliveryPolicy;
        $manages = $policy->manages($user);
        $tab = in_array($request->query('tab'), ['ready', 'road', 'done'], true) ? $request->query('tab') : 'ready';

        return Inertia::render('staff/deliveries/index', [
            'tab' => $tab,
            'manages' => $manages,
            'deliveries' => fn () => $this->runs($user, $manages, $policy),
            'cash' => fn () => $this->cash($user, $manages),
            'riders' => fn () => $manages
                ? User::query()->where('role', Role::Rider)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * @return Builder<Order>
     */
    private function mine(User $user, bool $manages): Builder
    {
        return Order::query()
            ->where('channel', OrderChannel::Delivery)
            ->when(! $manages, fn (Builder $query) => $query->where('rider_id', $user->id));
    }

    /**
     * @return Builder<Order>
     */
    private function doneToday(User $user, bool $manages): Builder
    {
        return $this->mine($user, $manages)
            ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Completed])
            ->whereHas('events', fn (Builder $events) => $events->where('to', OrderStatus::Delivered)->where('created_at', '>=', now()->startOfDay()));
    }

    /**
     * @return array{ready: list<array<string, mixed>>, road: list<array<string, mixed>>, done: list<array<string, mixed>>}
     */
    private function runs(User $user, bool $manages, DeliveryPolicy $policy): array
    {
        $with = ['lines.menuItem:id,is_alcoholic', 'rider:id,name'];

        $live = $this->mine($user, $manages)
            ->whereIn('status', [OrderStatus::Ready, OrderStatus::OutForDelivery])
            ->with($with)
            ->orderBy('ready_at')
            ->get();

        $done = $this->doneToday($user, $manages)->with($with)->latest('updated_at')->get();

        $failures = ActivityLog::query()
            ->where('event', 'delivery.failed')
            ->where('subject_type', (new Order)->getMorphClass())
            ->whereIn('subject_id', $live->pluck('id'))
            ->latest('id')
            ->get()
            ->unique('subject_id')
            ->keyBy('subject_id');

        $card = fn (Order $order) => $this->card($order, $policy, $failures->get($order->id)?->properties['reason'] ?? null);

        return [
            'ready' => array_values($live->where('status', OrderStatus::Ready)->map($card)->all()),
            'road' => array_values($live->where('status', OrderStatus::OutForDelivery)->map($card)->all()),
            'done' => array_values($done->map($card)->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Order $order, DeliveryPolicy $policy, ?string $failure): array
    {
        $items = (int) $order->lines->sum('quantity');
        $address = $order->address ?? [];
        $name = trim((string) ($order->contact['name'] ?? ''));

        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'first_name' => explode(' ', $name)[0] ?: $name,
            'phone' => $order->contact['phone'] ?? null,
            'address' => collect([$address['line1'] ?? null, $address['line2'] ?? null, $address['city'] ?? null])->filter()->implode(', '),
            'postcode' => $order->postcode,
            'instructions' => $address['instructions'] ?? null,
            'items' => $items,
            'bags' => max(1, (int) ceil($items / self::ITEMS_PER_BAG)),
            'total' => $order->total,
            'cash_due' => $policy->collectsCash($order),
            'cash_collected' => $order->cash_collected,
            'age_check' => $order->age_confirmed || $order->lines->contains(fn (OrderLine $line) => $line->menuItem?->is_alcoholic === true),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'rider' => $order->rider ? ['id' => $order->rider->id, 'name' => $order->rider->name] : null,
            'last_failure' => $failure,
        ];
    }

    /**
     * Cash taken today, to hand over at the end of a shift. Managers see it per rider.
     *
     * @return array{total: int, by_rider: list<array{name: string, total: int}>}
     */
    private function cash(User $user, bool $manages): array
    {
        /** @var Collection<int, Order> $orders */
        $orders = $this->doneToday($user, $manages)->whereNotNull('cash_collected')->with('rider:id,name')->get();

        return [
            'total' => (int) $orders->sum('cash_collected'),
            'by_rider' => $manages
                ? array_values($orders->groupBy(fn (Order $order) => $order->rider->name ?? '')
                    ->map(fn (Collection $group, string $name) => ['name' => $name, 'total' => (int) $group->sum('cash_collected')])
                    ->all())
                : [],
        ];
    }
}
