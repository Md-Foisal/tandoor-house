<?php

namespace App\Actions\Menu;

use App\Http\Controllers\MenuController;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\Cache;

class SetItemFlag
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function handle(MenuItem $item, string $flag, bool $value, User $by): void
    {
        if ($item->{$flag} === $value) {
            return;
        }

        $item->update([$flag => $value]);

        $this->activity->record(
            "menu.item.{$flag}",
            __("staff_menu.log.{$flag}_".($value ? 'on' : 'off'), ['name' => $item->name]),
            $item,
            [$flag => $value],
            $by,
        );
        Cache::forget(MenuController::CACHE_KEY);
    }
}
