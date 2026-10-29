<?php

namespace App\Policies;

use App\Models\User;

/**
 * Rules inside the stock section, on top of the "staff.inventory" gate.
 * Not auto-discovered: the inventory controllers and requests call it directly.
 */
class InventoryPolicy
{
    /** Ingredients, recipes, suppliers and purchase orders belong to managers and owners. */
    public function manage(User $user): bool
    {
        return $user->role->isManagement();
    }

    /** Kitchen staff log waste, deliveries and stock counts as well. */
    public function log(User $user): bool
    {
        return $user->can('staff.inventory');
    }
}
