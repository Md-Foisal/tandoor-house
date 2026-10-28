<?php

namespace App\Actions\Floor;

use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Models\TableRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandleTableRequest
{
    public function handle(TableRequest $request, User $by): TableRequest
    {
        if ($request->handled_at !== null) {
            throw ValidationException::withMessages(['request' => __('staff_floor.errors.request_handled')]);
        }

        $request->loadMissing('diningTable');

        return DB::transaction(function () use ($request, $by) {
            $request->forceFill(['handled_at' => now(), 'handled_by' => $by->id])->save();

            $table = $request->diningTable;

            if ($request->type === TableRequestType::Bill && $table->status === TableStatus::Seated) {
                $table->forceFill(['status' => TableStatus::BillRequested])->save();
            }

            return $request;
        });
    }
}
