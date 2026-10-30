<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Staff\OrderBoardPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderBoardController extends Controller
{
    private const FINISHED = [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled];

    /** Only the Done column is capped; live orders always all show. */
    private const DONE_LIMIT = 100;

    public function index(Request $request, OrderData $data): Response
    {
        $channel = OrderChannel::tryFrom((string) $request->query('channel'));
        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $search = Str::of((string) $request->query('q'))->trim()->limit(60, '')->toString();
        $viewer = $request->user();

        return Inertia::render('staff/orders/index', [
            'filters' => [
                'channel' => $channel?->value,
                'status' => $status?->value,
                'q' => $search,
            ],
            'orders' => fn () => $this->board($channel, $status, $search, $data, $viewer),
        ]);
    }

    public function show(Request $request, Order $order, OrderData $data, OrderBoardPolicy $policy): Response
    {
        $user = $request->user();
        $order->load(['lines.menuItem:id,is_alcoholic', 'diningTable:id,label', 'rider:id,name', 'events.user:id,name', 'payments']);

        return Inertia::render('staff/orders/show', [
            'order' => $data->detail($order, $user),
            'abilities' => [
                'edit' => $policy->canEdit($order),
                'refund' => $policy->canRefund($user) && $policy->isRefundable($order),
                'refund_limit' => $policy->refundLimit($user),
                'take_payment' => $this->amountDue($order, $user, $policy) !== null,
                'payment_due' => $this->amountDue($order, $user, $policy),
                'assign_rider' => $policy->canAssignRider($user, $order),
            ],
            'riders' => fn () => $policy->canAssignRider($user, $order)
                ? User::query()->where('role', Role::Rider)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /** Pence waiting to be taken at the venue, when this user may take it. */
    private function amountDue(Order $order, User $user, OrderBoardPolicy $policy): ?int
    {
        if (! $policy->takePayment($user, $order)->allowed()) {
            return null;
        }

        $pending = $order->payments->first(fn (Payment $p) => $p->gateway === 'venue' && $p->status === PaymentStatus::Pending);

        if ($pending !== null) {
            return $pending->amount;
        }

        $legacy = $order->payments->isEmpty()
            && $order->payment_status === PaymentStatus::Pending
            && in_array($order->payment_method, [PaymentMethod::PayAtVenue, PaymentMethod::Cash], true);

        return $legacy ? $order->total : null;
    }

    /**
     * Live orders oldest first, then finished ones newest first. Today's
     * finished orders show by default; a search looks back a week. The search
     * runs in SQL so the Done cap never hides a match.
     *
     * @return list<array<string, mixed>>
     */
    private function board(?OrderChannel $channel, ?OrderStatus $status, string $search, OrderData $data, User $viewer): array
    {
        $base = fn (): Builder => Order::query()
            ->with(['lines.menuItem:id,is_alcoholic', 'diningTable:id,label', 'rider:id,name'])
            ->when($channel !== null, fn (Builder $q) => $q->where('channel', $channel))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $this->matching($q, $search));

        $live = $base()->whereNotIn('status', self::FINISHED)->orderBy('placed_at')->get();

        $finished = $base()
            ->whereIn('status', self::FINISHED)
            ->where('placed_at', '>=', $search !== '' ? now()->subDays(7) : now()->startOfDay())
            ->orderByDesc('placed_at')
            ->limit(self::DONE_LIMIT)
            ->get();

        return array_values($live->concat($finished)->map(fn (Order $order) => $data->card($order, $viewer))->all());
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function matching(Builder $query, string $search): Builder
    {
        $term = '%'.str_replace(['%', '_', '\\'], '', $search).'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $mysql = DB::getDriverName() !== 'sqlite';

        return $query->where(function (Builder $q) use ($term, $digits, $mysql) {
            $q->where('number', 'like', $term)
                ->orWhere('contact->name', 'like', $term)
                ->orWhereHas('diningTable', fn (Builder $t) => $t->where('label', 'like', $term));

            if ($digits !== '') {
                $mysql
                    ? $q->orWhereRaw("replace(replace(replace(replace(json_unquote(json_extract(contact, '$.phone')), ' ', ''), '-', ''), '+', ''), '(', '') like ?", ['%'.$digits.'%'])
                    : $q->orWhereRaw("replace(replace(replace(replace(json_extract(contact, '$.phone'), ' ', ''), '-', ''), '+', ''), '(', '') like ?", ['%'.$digits.'%']);
            }
        });
    }
}
