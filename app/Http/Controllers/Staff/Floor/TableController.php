<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Actions\Floor\ChangeTableStatus;
use App\Actions\Floor\TransferTable;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Floor\ChangeTableStatusRequest;
use App\Http\Requests\Staff\Floor\TransferTableRequest;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TableController extends Controller
{
    public function status(ChangeTableStatusRequest $request, DiningTable $table, ChangeTableStatus $change): RedirectResponse
    {
        $to = TableStatus::from($request->string('status')->toString());
        $change->handle($table, $to, $this->staff($request->user()), $request->integer('party_size') ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_floor.toast.status', [
            'table' => $table->label,
            'status' => __('staff_floor.status.'.$to->value),
        ])]);

        return back();
    }

    public function transfer(TransferTableRequest $request, DiningTable $table, TransferTable $transfer): RedirectResponse
    {
        $to = DiningTable::query()->findOrFail($request->integer('to'));
        $transfer->handle($table, $to, $this->staff($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_floor.toast.transferred', ['from' => $table->label, 'to' => $to->label])]);

        return back();
    }

    private function staff(?User $user): User
    {
        return $user ?? abort(403);
    }
}
