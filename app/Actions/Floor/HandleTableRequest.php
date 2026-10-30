<?php

namespace App\Actions\Floor;

use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Events\TableRequestRaised;
use App\Models\DiningTable;
use App\Models\TableRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandleTableRequest
{
    public function handle(TableRequest $request, User $by): TableRequest
    {
        return DB::transaction(function () use ($request, $by) {
            $request = TableRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->handled_at !== null) {
                throw ValidationException::withMessages(['request' => __('staff_floor.errors.request_handled')]);
            }

            $request->forceFill(['handled_at' => now(), 'handled_by' => $by->id])->save();

            $table = DiningTable::query()->whereKey($request->dining_table_id)->lockForUpdate()->firstOrFail();

            if ($request->type === TableRequestType::Bill && $table->status === TableStatus::Seated) {
                $table->forceFill(['status' => TableStatus::BillRequested])->save();
            }

            TableRequestRaised::dispatch($request);

            return $request;
        });
    }
}
