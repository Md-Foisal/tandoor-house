<?php

namespace App\Actions\Floor;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnmergeTable
{
    public function __construct(private readonly ActivityRecorder $log) {}

    /**
     * Splitting a joined table frees just that table; splitting the lead
     * frees every table joined to it.
     */
    public function handle(DiningTable $table, User $by): DiningTable
    {
        return DB::transaction(function () use ($table, $by) {
            $table = DiningTable::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();
            $isChild = $table->merged_into_id !== null;
            $lead = $isChild ? DiningTable::query()->whereKey($table->merged_into_id)->lockForUpdate()->firstOrFail() : $table;
            $released = $isChild ? collect([$table]) : DiningTable::query()->where('merged_into_id', $table->id)->lockForUpdate()->get();

            if ($released->isEmpty()) {
                throw ValidationException::withMessages(['tables' => __('staff_floor.errors.not_merged', ['table' => $table->label])]);
            }

            foreach ($released as $child) {
                $child->forceFill(['merged_into_id' => null, 'status' => TableStatus::Free])->save();
            }

            $this->log->record('table.unmerged', __('staff_floor.log.unmerged', ['lead' => $lead->label, 'tables' => $released->pluck('label')->implode(', ')]), $lead, [
                'lead' => $lead->label,
                'released' => $released->pluck('label')->values()->all(),
            ], $by);

            return $lead;
        });
    }
}
