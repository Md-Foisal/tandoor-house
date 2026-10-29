<?php

namespace App\Actions\Menu;

use App\Http\Controllers\MenuController;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RecordMenuChange
{
    /**
     * Writes the activity row and drops the cached public menu.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, Model $subject, User $by, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'user_id' => $by->id,
            'description' => $description,
            'properties' => $properties,
        ]);

        self::forgetMenu();
    }

    public static function forgetMenu(): void
    {
        Cache::forget(MenuController::CACHE_KEY);
    }
}
