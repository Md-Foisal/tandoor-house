<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public const TERMINAL = [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled];

    public function __invoke(Request $request, Settings $settings): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('account/show', [
            'firstName' => strtok($user->name, ' ') ?: $user->name,
            'isStaff' => $user->isStaff(),
            'loyalty' => $this->loyalty($user, $settings),
            'activeOrders' => $this->activeOrders($user),
            'recentOrders' => $this->recentOrders($user),
            'promos' => $this->promos(),
            'reservations' => $this->reservations($user),
            'address' => $this->address($user),
        ]);
    }

    /**
     * @return array{points: int, reward_points: int, reward_value: int, rewards_ready: int, points_to_next: int, progress: int, transactions: array<int, array{id: int, points: int, reason: string, created_at: string}>}
     */
    private function loyalty(User $user, Settings $settings): array
    {
        $rewardPoints = max(1, (int) $settings->get('loyalty.reward_points', 150));
        $points = max(0, $user->loyalty_points);
        $into = $points % $rewardPoints;

        return [
            'points' => $points,
            'reward_points' => $rewardPoints,
            'reward_value' => (int) $settings->get('loyalty.reward_value', 1000),
            'rewards_ready' => intdiv($points, $rewardPoints),
            'points_to_next' => $rewardPoints - $into,
            'progress' => (int) floor($into / $rewardPoints * 100),
            'transactions' => $user->loyaltyTransactions()
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (LoyaltyTransaction $transaction) => [
                    'id' => $transaction->id,
                    'points' => $transaction->points,
                    'reason' => $transaction->reason,
                    'created_at' => $transaction->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array{number: string, status: string, channel: string, total: int, placed_at: string}>
     */
    private function activeOrders(User $user): array
    {
        return $user->orders()
            ->whereNotIn('status', self::TERMINAL)
            ->latest('placed_at')
            ->get()
            ->map(fn (Order $order) => [
                'number' => $order->number,
                'status' => $order->status->value,
                'channel' => $order->channel->value,
                'total' => $order->total,
                'placed_at' => $order->placed_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{number: string, placed_at: string, total: int, lines: array<int, array{name: string, quantity: int}>}>
     */
    private function recentOrders(User $user): array
    {
        return $user->orders()
            ->where('status', OrderStatus::Completed)
            ->with('lines')
            ->latest('placed_at')
            ->limit(3)
            ->get()
            ->map(fn (Order $order) => [
                'number' => $order->number,
                'placed_at' => $order->placed_at->toIso8601String(),
                'total' => $order->total,
                'lines' => $order->lines
                    ->map(fn (OrderLine $line) => ['name' => $line->name, 'quantity' => $line->quantity])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, description: string, minimum_order: int, ends_at: string|null}>
     */
    private function promos(): array
    {
        return PromoCode::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'))
            ->orderBy('code')
            ->get()
            ->map(fn (PromoCode $promo) => [
                'code' => $promo->code,
                'description' => $promo->description,
                'minimum_order' => $promo->minimum_order,
                'ends_at' => $promo->ends_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{reference: string, starts_at: string, party_size: int, status: string}>
     */
    private function reservations(User $user): array
    {
        return $user->reservations()
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'reference' => $reservation->reference,
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'party_size' => $reservation->party_size,
                'status' => $reservation->status->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, line1: string, line2: string|null, city: string, postcode: string}|null
     */
    private function address(User $user): ?array
    {
        $address = $user->addresses()->orderByDesc('is_default')->orderBy('id')->first();

        return $address instanceof Address ? [
            'label' => $address->label,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'postcode' => $address->postcode,
        ] : null;
    }
}
