<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StaffArea;
use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function __construct(private readonly InventoryPolicy $inventory) {}

    public function create(User $user): bool
    {
        return StaffArea::Menu->allows($user->role);
    }

    public function update(User $user, MenuItem $item): bool
    {
        return StaffArea::Menu->allows($user->role);
    }

    /** VAT is a money setting, so only the owner may give a dish a different rate. */
    public function setVatRate(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    /** Availability and the special flag on the menu manager. */
    public function flag(User $user, MenuItem $item): bool
    {
        return StaffArea::Menu->allows($user->role);
    }

    /** The kitchen pulls a dish the moment it runs out. */
    public function markSoldOut(User $user, MenuItem $item): bool
    {
        return StaffArea::Kitchen->allows($user->role);
    }

    /** Recipes carry cost and margin, so they sit with stock management. */
    public function updateRecipe(User $user, MenuItem $item): bool
    {
        return $this->inventory->manage($user);
    }
}
