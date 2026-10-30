<?php

namespace App\Policies;

use App\Enums\StaffArea;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return StaffArea::Activity->allows($user->role);
    }

    public function export(User $user): bool
    {
        return StaffArea::Activity->allows($user->role);
    }
}
