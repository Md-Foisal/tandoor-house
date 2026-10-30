<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function __construct(private readonly InventoryPolicy $inventory) {}

    public function viewAny(User $user): bool
    {
        return $this->inventory->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->inventory->manage($user);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->inventory->manage($user);
    }
}
