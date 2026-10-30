<?php

namespace App\Actions\Menu;

use App\Http\Controllers\MenuController;
use App\Models\Category;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function handle(Category $category, User $by): void
    {
        if ($category->items()->exists()) {
            throw ValidationException::withMessages(['category' => __('staff_menu.errors.category_not_empty', ['name' => $category->name])]);
        }

        $name = $category->name;
        $category->delete();

        $this->activity->record('menu.category.deleted', __('staff_menu.log.category_deleted', ['name' => $name]), $category, by: $by);
        Cache::forget(MenuController::CACHE_KEY);
    }
}
