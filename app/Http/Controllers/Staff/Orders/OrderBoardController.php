<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Policies\Staff\OrderBoardPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderBoardController extends Controller
{
    public function index(Request $request, OrderData $data): Response
    {
        $channel = OrderChannel::tryFrom((string) $request->query('channel'));
        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $search = Str::of((string) $request->query('q'))->trim()->limit(60, '')->toString();

        return Inertia::render('staff/orders/index', [
            'filters' => [
                'channel' => $channel?->value,
                'status' => $status?->value,
                'q' => $search,
            ],
            'orders' => fn () => $this->board($channel, $status, $search, $data),
        ]);
    }

    public function show(Request $request, Order $order, OrderData $data): Response
    {
        $policy = new OrderBoardPolicy;
        $user = $request->user();
        $order->load(['lines.menuItem:id,is_alcoholic', 'diningTable:id,label', 'rider:id,name', 'events.user:id,name', 'payments']);
        $detail = $data->detail($order);

        return Inertia::render('staff/orders/show', [
            'order' => $detail,
            'abilities' => [
                'edit' => $policy->canEdit($order),
                'refund' => $policy->canRefund($user) && $policy->isRefundable($order),
                'refund_limit' => $policy->refundLimit($user),
                'assign_rider' => $policy->canAssignRider($user, $order),
            ],
            'riders' => fn () => User::query()->where('role', Role::Rider)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Today's orders and anything still live; a search looks back a week.
     *
     * @return list<array<string, mixed>>
     */
    private function board(?OrderChannel $channel, ?OrderStatus $status, string $search, OrderData $data): array
    {
        $query = Order::query()
            ->with(['lines.menuItem:id,is_alcoholic', 'diningTable:id,label', 'rider:id,name'])
            ->orderBy('placed_at')
            ->limit(300);

        if ($search !== '') {
            $query->where('placed_at', '>=', now()->subDays(7));
        } else {
            $query->where(fn ($q) => $q
                ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled])
                ->orWhere('placed_at', '>=', now()->startOfDay()));
        }

        if ($channel !== null) {
            $query->where('channel', $channel);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $orders = $query->get();

        if ($search !== '') {
            $needle = Str::lower($search);
            $digits = preg_replace('/\D+/', '', $search) ?? '';

            $orders = $orders->filter(fn (Order $order) => Str::contains(Str::lower($order->number), $needle)
                || Str::contains(Str::lower($order->contact['name'] ?? ''), $needle)
                || Str::contains(Str::lower($order->diningTable->label ?? ''), $needle)
                || ($digits !== '' && Str::contains((string) preg_replace('/\D+/', '', $order->contact['phone'] ?? ''), $digits)));
        }

        return array_values($orders->map(fn (Order $order) => $data->card($order))->all());
    }
}
