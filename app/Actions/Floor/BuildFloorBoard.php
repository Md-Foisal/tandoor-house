<?php

namespace App\Actions\Floor;

use App\Enums\ReservationStatus;
use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TableRequest;
use Illuminate\Support\Collection;

/**
 * Everything the floor page draws: every table with its party, requests and
 * orders for today, and the bookings due in the next two hours.
 */
class BuildFloorBoard
{
    /**
     * @return array{tables: list<array<string, mixed>>, upcoming: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $now = now();
        $startOfDay = now('Europe/London')->startOfDay();

        /** @var Collection<int, DiningTable> $tables */
        $tables = DiningTable::query()->orderBy('id')->get();
        $children = $tables->whereNotNull('merged_into_id')->groupBy('merged_into_id');

        $requests = TableRequest::query()->whereNull('handled_at')->orderBy('created_at')->get()->groupBy('dining_table_id');

        $orders = Order::query()
            ->whereNotNull('dining_table_id')
            ->where('placed_at', '>=', $startOfDay)
            ->orderBy('placed_at')
            ->get()
            ->groupBy('dining_table_id');

        $reservations = Reservation::query()
            ->whereNotNull('dining_table_id')
            ->whereIn('status', [ReservationStatus::Seated, ReservationStatus::Confirmed, ReservationStatus::PendingDeposit])
            ->where('starts_at', '>=', $startOfDay)
            ->where('starts_at', '<=', $now->copy()->addHours(2))
            ->orderBy('starts_at')
            ->get()
            ->groupBy('dining_table_id');

        $labels = $tables->pluck('label', 'id');

        $rows = array_values($tables->map(function (DiningTable $table) use ($children, $requests, $orders, $reservations, $labels) {
            $group = $children->get($table->id, collect());
            $ids = $group->pluck('id')->prepend($table->id);

            $groupOrders = $ids->flatMap(fn (int $id) => $orders->get($id, collect()))->sortBy('placed_at')->values();
            $open = $groupOrders->reject(fn (Order $order) => in_array($order->status, TableLayout::closedStatuses(), true));
            $groupRequests = $ids->flatMap(fn (int $id) => $requests->get($id, collect()))->values();
            $groupBookings = $ids->flatMap(fn (int $id) => $reservations->get($id, collect()))->sortBy('starts_at')->values();

            $seatedBooking = $groupBookings->first(fn (Reservation $r) => $r->status === ReservationStatus::Seated);
            $nextBooking = $groupBookings->first(fn (Reservation $r) => $r->status !== ReservationStatus::Seated);
            $billOpen = $groupRequests->contains(fn (TableRequest $r) => $r->type === TableRequestType::Bill);
            $status = $table->status === TableStatus::Seated && $billOpen ? TableStatus::BillRequested : $table->status;
            $occupied = in_array($status, [TableStatus::Seated, TableStatus::BillRequested], true);

            $party = null;

            if ($occupied && $table->merged_into_id === null) {
                $party = $seatedBooking !== null
                    ? ['kind' => 'booking', 'name' => $seatedBooking->name, 'size' => $seatedBooking->party_size, 'reference' => $seatedBooking->reference]
                    : ['kind' => 'walk_in', 'name' => null, 'size' => null, 'reference' => null];
            }

            $seatedAt = $occupied
                ? ($seatedBooking->seated_at ?? $open->first()->placed_at ?? $table->updated_at)
                : null;

            return [
                'id' => $table->id,
                'label' => $table->label,
                'seats' => $table->seats,
                'group_seats' => $table->seats + (int) $group->sum('seats'),
                'area' => $table->area,
                'x' => $table->x,
                'y' => $table->y,
                'width' => $table->width,
                'height' => $table->height,
                'shape' => $table->shape,
                'status' => $status->value,
                'qr_token' => $table->qr_token,
                'merged_into_id' => $table->merged_into_id,
                'merged_into_label' => $table->merged_into_id !== null ? $labels->get($table->merged_into_id) : null,
                'merged_labels' => $group->pluck('label')->values()->all(),
                'party' => $party,
                'booking' => $nextBooking === null ? null : [
                    'id' => $nextBooking->id,
                    'name' => $nextBooking->name,
                    'party_size' => $nextBooking->party_size,
                    'starts_at' => $nextBooking->starts_at->toIso8601String(),
                ],
                'seated_at' => $seatedAt?->toIso8601String(),
                'open_total' => (int) $open->sum('total'),
                'open_orders' => $open->count(),
                'requests' => $groupRequests->map(fn (TableRequest $r) => [
                    'id' => $r->id,
                    'type' => $r->type->value,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->all(),
                'orders' => $groupOrders->map(fn (Order $order) => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'status' => $order->status->value,
                    'total' => $order->total,
                    'placed_at' => $order->placed_at->toIso8601String(),
                ])->all(),
            ];
        })->all());

        $upcoming = array_values(Reservation::query()
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit])
            ->where('starts_at', '>=', $now->copy()->subMinutes(30))
            ->where('starts_at', '<=', $now->copy()->addHours(2))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'name' => $r->name,
                'party_size' => $r->party_size,
                'starts_at' => $r->starts_at->toIso8601String(),
                'table_id' => $r->dining_table_id,
                'table_label' => $r->dining_table_id !== null ? $labels->get($r->dining_table_id) : null,
                'occasion' => $r->occasion,
                'deposit_pending' => $r->status === ReservationStatus::PendingDeposit,
            ])
            ->all());

        return ['tables' => $rows, 'upcoming' => $upcoming];
    }
}
