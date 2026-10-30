<?php

namespace App\Policies;

use App\Enums\StaffArea;
use App\Models\DiningTable;
use App\Models\User;

/** Everyone working the floor runs the table map. */
class DiningTablePolicy
{
    public function updateStatus(User $user, DiningTable $table): bool
    {
        return StaffArea::Floor->allows($user->role);
    }

    public function transfer(User $user, DiningTable $table): bool
    {
        return StaffArea::Floor->allows($user->role);
    }

    public function merge(User $user): bool
    {
        return StaffArea::Floor->allows($user->role);
    }

    public function unmerge(User $user, DiningTable $table): bool
    {
        return StaffArea::Floor->allows($user->role);
    }
}
