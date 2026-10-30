<?php

namespace App\Http\Controllers\Staff\Menu;

use App\Actions\Menu\DeleteCategory;
use App\Actions\Menu\MoveCategory;
use App\Actions\Menu\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Menu\MoveCategoryRequest;
use App\Http\Requests\Staff\Menu\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function store(SaveCategoryRequest $request, SaveCategory $action): RedirectResponse
    {
        $category = $action->handle($request->validated(), $request->user());

        return $this->done(__('staff_menu.flash.category_created', ['name' => $category->name]));
    }

    public function update(SaveCategoryRequest $request, Category $category, SaveCategory $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $category);

        return $this->done(__('staff_menu.flash.category_updated', ['name' => $category->name]));
    }

    public function destroy(Category $category, DeleteCategory $action): RedirectResponse
    {
        $name = $category->name;
        $action->handle($category, request()->user());

        return $this->done(__('staff_menu.flash.category_deleted', ['name' => $name]));
    }

    public function move(MoveCategoryRequest $request, Category $category, MoveCategory $action): RedirectResponse
    {
        $action->handle($category, $request->string('direction')->toString(), $request->user());

        return back();
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
