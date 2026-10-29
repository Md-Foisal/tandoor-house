<?php

namespace App\Actions\Menu;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoveCategory
{
    public function handle(Category $category, string $direction, User $by): void
    {
        DB::transaction(function () use ($category, $direction, $by) {
            $ordered = Category::query()->orderBy('position')->orderBy('id')->get()->values();
            $from = $ordered->search(fn (Category $row) => $row->is($category));

            if ($from === false) {
                return;
            }

            $to = $direction === 'up' ? $from - 1 : $from + 1;

            if (! isset($ordered[$to])) {
                return;
            }

            $moved = $ordered->splice($from, 1)->first();
            $ordered->splice($to, 0, [$moved]);

            foreach ($ordered->values() as $index => $row) {
                if ($row->position !== $index) {
                    $row->update(['position' => $index]);
                }
            }

            RecordMenuChange::record('menu.category.moved', $category, $by, __('staff_menu.log.category_moved', ['name' => $category->name]));
        });
    }
}
