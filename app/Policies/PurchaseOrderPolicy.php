<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/** Ordering from suppliers spends money, so it is for managers and owners. */
class PurchaseOrderPolicy
{
    public function __construct(private readonly InventoryPolicy $inventory) {}

    public function viewAny(User $user): bool
    {
        return $this->inventory->manage($user);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->inventory->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->inventory->manage($user);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $this->inventory->manage($user);
    }

    public function send(User $user, PurchaseOrder $order): bool
    {
        return $this->inventory->manage($user);
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $this->inventory->manage($user);
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $this->inventory->manage($user);
    }
}
