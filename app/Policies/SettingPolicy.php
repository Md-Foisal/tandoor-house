<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StaffArea;
use App\Models\User;

/**
 * Managers run day-to-day settings; anything that sets prices, fees or
 * payments (VAT, service charge, tips, deposits, delivery zone fees) is the
 * owner's.
 */
class SettingPolicy
{
    public function update(User $user): bool
    {
        return StaffArea::Settings->allows($user->role);
    }

    public function updateMoney(User $user): bool
    {
        return StaffArea::Settings->allows($user->role) && $user->role === Role::Owner;
    }
}
