<?php

namespace App\Actions\Floor;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnmergeTable
{
    public function __construct(private readonly RecordFloorActivity $log) {}

    /**
     * Splitting a joined table frees just that table; splitting the lead
     * frees every table joined to it.
     */
    public function handle(DiningTable $table, User $by): DiningTable
    {
        $isChild = $table->merged_into_id !== null;
        $lead = $isChild ? DiningTable::query()->findOrFail($table->merged_into_id) : $table;
        $released = $isChild ? collect([$table]) : DiningTable::query()->where('merged_into_id', $table->id)->get();

        if ($released->isEmpty()) {
            throw ValidationException::withMessages(['tables' => __('staff_floor.errors.not_merged', ['table' => $table->label])]);
        }

        return DB::transaction(function () use ($released, $lead, $by) {
            foreach ($released as $child) {
                $child->forceFill(['merged_into_id' => null, 'status' => TableStatus::Free])->save();
            }

            $this->log->handle('table.unmerged', $lead, $by, __('staff_floor.log.unmerged', ['lead' => $lead->label, 'tables' => $released->pluck('label')->implode(', ')]), [
                'lead' => $lead->label,
                'released' => $released->pluck('label')->values()->all(),
            ]);

            return $lead;
        });
    }
}
