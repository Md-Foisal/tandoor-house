<?php

namespace App\Actions\Menu;

use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function handle(Category $category, User $by): void
    {
        if ($category->items()->exists()) {
            throw ValidationException::withMessages(['category' => __('staff_menu.errors.category_not_empty', ['name' => $category->name])]);
        }

        $name = $category->name;
        $category->delete();

        RecordMenuChange::record('menu.category.deleted', $category, $by, __('staff_menu.log.category_deleted', ['name' => $name]));
    }
}
