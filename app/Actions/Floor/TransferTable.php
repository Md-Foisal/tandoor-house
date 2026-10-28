<?php

namespace App\Actions\Floor;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferTable
{
    public function __construct(private readonly RecordFloorActivity $log) {}

    public function handle(DiningTable $from, DiningTable $to, User $by): DiningTable
    {
        if ($from->is($to)) {
            $this->fail(__('staff_floor.errors.transfer_same'));
        }

        if (! in_array($from->status, [TableStatus::Seated, TableStatus::BillRequested], true) || $from->merged_into_id !== null) {
            $this->fail(__('staff_floor.errors.transfer_source', ['table' => $from->label]));
        }

        if (DiningTable::query()->where('merged_into_id', $from->id)->exists()) {
            $this->fail(__('staff_floor.errors.transfer_joined', ['table' => $from->label]));
        }

        if ($to->status !== TableStatus::Free || $to->merged_into_id !== null || DiningTable::query()->where('merged_into_id', $to->id)->exists()) {
            $this->fail(__('staff_floor.errors.transfer_busy', ['table' => $to->label]));
        }

        $reservation = Reservation::query()
            ->where('dining_table_id', $from->id)
            ->where('status', ReservationStatus::Seated)
            ->first();

        if ($reservation !== null && $reservation->party_size > $to->seats) {
            $this->fail(__('staff_floor.errors.transfer_small', ['table' => $to->label, 'seats' => $to->seats, 'size' => $reservation->party_size]));
        }

        return DB::transaction(function () use ($from, $to, $by, $reservation) {
            $moved = Order::query()
                ->where('dining_table_id', $from->id)
                ->whereNotIn('status', TableLayout::closedStatuses())
                ->update(['dining_table_id' => $to->id]);

            $from->requests()->whereNull('handled_at')->update(['dining_table_id' => $to->id]);
            $reservation?->forceFill(['dining_table_id' => $to->id])->save();

            $to->forceFill(['status' => $from->status])->save();
            $from->forceFill(['status' => TableStatus::NeedsCleaning])->save();

            $this->log->handle('table.transferred', $from, $by, __('staff_floor.log.transferred', ['from' => $from->label, 'to' => $to->label]), [
                'from' => $from->label,
                'to' => $to->label,
                'orders_moved' => $moved,
            ]);

            return $to;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['to' => $message]);
    }
}
