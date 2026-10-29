<?php

namespace App\Actions\Menu;

use App\Models\MenuItem;
use App\Models\User;

class SetItemFlag
{
    public function handle(MenuItem $item, string $flag, bool $value, User $by): void
    {
        if ($item->{$flag} === $value) {
            return;
        }

        $item->update([$flag => $value]);

        RecordMenuChange::record(
            "menu.item.{$flag}",
            $item,
            $by,
            __("staff_menu.log.{$flag}_".($value ? 'on' : 'off'), ['name' => $item->name]),
            [$flag => $value],
        );
    }
}
