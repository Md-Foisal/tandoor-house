<?php

namespace App\Policies;

use App\Models\User;

/**
 * Rules inside the stock section, on top of the "staff.inventory" gate.
 * Not auto-discovered: resolve it from the container.
 */
class InventoryPolicy
{
    /**
     * Suppliers, purchase orders, recipe costs and margins, and everything that
     * edits ingredients belong to managers and owners.
     */
    public function manage(User $user): bool
    {
        return $user->can('staff.inventory') && $user->role->isManagement();
    }

    /** Kitchen staff log waste, stock-in and stock counts; they never see supplier or margin figures. */
    public function log(User $user): bool
    {
        return $user->can('staff.inventory');
    }
}
