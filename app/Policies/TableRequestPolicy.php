<?php

namespace App\Policies;

use App\Enums\StaffArea;
use App\Models\TableRequest;
use App\Models\User;

/** Calls for a waiter and bill requests from the table QR page. */
class TableRequestPolicy
{
    public function handle(User $user, TableRequest $request): bool
    {
        return StaffArea::Floor->allows($user->role);
    }
}
