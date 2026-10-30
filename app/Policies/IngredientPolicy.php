<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

/**
 * Kitchen staff count, receive and waste stock; only managers and owners
 * create or edit ingredients, since that is where costs live.
 */
class IngredientPolicy
{
    public function __construct(private readonly InventoryPolicy $inventory) {}

    public function create(User $user): bool
    {
        return $this->inventory->manage($user);
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $this->inventory->manage($user);
    }

    public function stockIn(User $user, Ingredient $ingredient): bool
    {
        return $this->inventory->log($user);
    }

    public function adjust(User $user, Ingredient $ingredient): bool
    {
        return $this->inventory->log($user);
    }

    public function logWaste(User $user): bool
    {
        return $this->inventory->log($user);
    }
}
