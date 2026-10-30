<?php

namespace App\Actions\Floor;

use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeTableStatus
{
    public function __construct(private readonly ActivityRecorder $log) {}

    /**
     * Where a table may go from each status.
     *
     * @return list<TableStatus>
     */
    public static function nextFor(TableStatus $from): array
    {
        return match ($from) {
            TableStatus::Free => [TableStatus::Seated],
            TableStatus::Reserved => [TableStatus::Free],
            TableStatus::Seated => [TableStatus::BillRequested, TableStatus::NeedsCleaning],
            TableStatus::BillRequested => [TableStatus::Seated, TableStatus::NeedsCleaning],
            TableStatus::NeedsCleaning => [TableStatus::Free],
        };
    }

    public function handle(DiningTable $table, TableStatus $to, User $by, ?int $partySize = null): DiningTable
    {
        return DB::transaction(function () use ($table, $to, $by, $partySize) {
            $table = DiningTable::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();

            if ($table->merged_into_id !== null) {
                $lead = DiningTable::query()->findOrFail($table->merged_into_id);

                throw ValidationException::withMessages(['status' => __('staff_floor.errors.joined', ['lead' => $lead->label])]);
            }

            if (! in_array($to, self::nextFor($table->status), true)) {
                throw ValidationException::withMessages(['status' => __('staff_floor.errors.status', [
                    'table' => $table->label,
                    'from' => __('staff_floor.status.'.$table->status->value),
                    'to' => __('staff_floor.status.'.$to->value),
                ])]);
            }

            $from = $table->status;
            $seating = $to === TableStatus::Seated && $from === TableStatus::Free;

            if ($seating) {
                $seats = TableLayout::groupSeats($table);

                if ($partySize === null || $partySize < 1) {
                    throw ValidationException::withMessages(['party_size' => __('staff_floor.errors.party_required')]);
                }

                if ($partySize > $seats) {
                    throw ValidationException::withMessages(['party_size' => __('staff_floor.errors.party_too_big', ['table' => $table->label, 'seats' => $seats])]);
                }
            }

            $releases = in_array($to, [TableStatus::Free, TableStatus::NeedsCleaning], true);

            $table->forceFill(['status' => $to]);

            if ($seating) {
                $table->forceFill(['party_size' => $partySize, 'seated_at' => now()]);
            } elseif ($releases) {
                $table->forceFill(['party_size' => null, 'seated_at' => null]);
            }

            $table->save();

            if ($releases) {
                DiningTable::query()->where('merged_into_id', $table->id)->update(['merged_into_id' => null, 'status' => TableStatus::Free]);
                $table->requests()->whereNull('handled_at')->update(['handled_at' => now(), 'handled_by' => $by->id]);
            }

            if ($to === TableStatus::BillRequested) {
                $table->requests()->whereNull('handled_at')->where('type', TableRequestType::Bill)->update(['handled_at' => now(), 'handled_by' => $by->id]);
            }

            DiningTable::query()->where('merged_into_id', $table->id)->update(['status' => $to]);

            $this->log->record('table.status', $this->describe($table, $from, $to, $partySize), $table, [
                'from' => $from->value,
                'to' => $to->value,
                'party_size' => $partySize,
            ], $by);

            return $table;
        });
    }

    private function describe(DiningTable $table, TableStatus $from, TableStatus $to, ?int $partySize): string
    {
        if ($to === TableStatus::Seated && $from === TableStatus::Free) {
            return __('staff_floor.log.seated', ['table' => $table->label, 'size' => $partySize]);
        }

        return __('staff_floor.log.status', ['table' => $table->label, 'to' => __('staff_floor.status.'.$to->value)]);
    }
}
