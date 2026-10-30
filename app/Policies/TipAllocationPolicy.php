<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StaffArea;
use App\Models\User;

class TipAllocationPolicy
{
    public function record(User $user): bool
    {
        return StaffArea::Team->allows($user->role);
    }

    /** Paying tips out moves money, so it is the owner's call. */
    public function pay(User $user): bool
    {
        return StaffArea::Team->allows($user->role) && $user->role === Role::Owner;
    }

    public function export(User $user): bool
    {
        return StaffArea::Team->allows($user->role);
    }
}
