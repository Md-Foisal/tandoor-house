<?php

namespace App\Actions\Floor;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeTables
{
    public function __construct(private readonly ActivityRecorder $log) {}

    /**
     * @param  list<int>  $ids
     */
    public function handle(array $ids, ?int $leadId, User $by): DiningTable
    {
        return DB::transaction(function () use ($ids, $leadId, $by) {
            $tables = DiningTable::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

            if ($tables->count() < 2) {
                $this->fail(__('staff_floor.errors.merge_two'));
            }

            foreach ($tables as $table) {
                if ($table->merged_into_id !== null) {
                    $this->fail(__('staff_floor.errors.merge_joined', ['table' => $table->label]));
                }

                if (! in_array($table->status, [TableStatus::Free, TableStatus::Seated], true)) {
                    $this->fail(__('staff_floor.errors.merge_status', ['table' => $table->label]));
                }
            }

            if ($tables->pluck('area')->unique()->count() > 1) {
                $this->fail(__('staff_floor.errors.merge_area'));
            }

            $lead = $this->lead($tables, $leadId);

            $others = $tables->reject(fn (DiningTable $table) => $table->is($lead));
            $existingChildren = DiningTable::query()->where('merged_into_id', $lead->id)->lockForUpdate()->get();

            foreach ($others as $table) {
                if (DiningTable::query()->where('merged_into_id', $table->id)->exists()) {
                    $this->fail(__('staff_floor.errors.merge_has_group', ['table' => $table->label]));
                }
            }

            if (! TableLayout::connected($tables->concat($existingChildren))) {
                $this->fail(__('staff_floor.errors.merge_adjacent'));
            }

            foreach ($others as $table) {
                $table->forceFill(['merged_into_id' => $lead->id, 'status' => $lead->status, 'party_size' => null, 'seated_at' => null])->save();
            }

            $labels = $others->pluck('label')->implode(', ');
            $this->log->record('table.merged', __('staff_floor.log.merged', ['lead' => $lead->label, 'tables' => $labels]), $lead, [
                'lead' => $lead->label,
                'merged' => $others->pluck('label')->values()->all(),
            ], $by);

            return $lead;
        });
    }

    /**
     * @param  Collection<int, DiningTable>  $tables
     */
    private function lead(Collection $tables, ?int $leadId): DiningTable
    {
        $seated = $tables->where('status', TableStatus::Seated);

        if ($seated->count() > 1) {
            $this->fail(__('staff_floor.errors.merge_two_parties'));
        }

        $lead = $leadId === null
            ? ($seated->first() ?? $tables->first(fn (DiningTable $table) => DiningTable::query()->where('merged_into_id', $table->id)->exists()) ?? $tables->sortBy('id')->first())
            : $tables->firstWhere('id', $leadId);

        if (! $lead instanceof DiningTable) {
            $this->fail(__('staff_floor.errors.merge_lead'));
        }

        if ($seated->isNotEmpty() && $seated->first()->isNot($lead)) {
            $this->fail(__('staff_floor.errors.merge_lead_seated', ['table' => $seated->first()->label]));
        }

        return $lead;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['tables' => $message]);
    }
}
