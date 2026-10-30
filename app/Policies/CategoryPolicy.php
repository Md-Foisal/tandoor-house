<?php

namespace App\Policies;

use App\Enums\StaffArea;
use App\Models\Category;
use App\Models\User;

/** Menu sections belong to managers and owners. */
class CategoryPolicy
{
    public function create(User $user): bool
    {
        return StaffArea::Menu->allows($user->role);
    }

    public function update(User $user, Category $category): bool
    {
        return StaffArea::Menu->allows($user->role);
    }

    public function delete(User $user, Category $category): bool
    {
        return StaffArea::Menu->allows($user->role);
    }

    public function reorder(User $user, Category $category): bool
    {
        return StaffArea::Menu->allows($user->role);
    }
}
