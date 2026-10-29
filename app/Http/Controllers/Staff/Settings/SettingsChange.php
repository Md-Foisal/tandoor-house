<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Saves one section's settings, logs what changed (before and after) and
 * flushes the settings cache. Returns false when nothing actually changed.
 */
class SettingsChange
{
    /**
     * @param  array<string, mixed>  $values  setting key => new value
     */
    public static function save(User $user, string $section, array $values, string $description): bool
    {
        $settings = app(Settings::class);
        $before = [];
        $after = [];

        foreach ($values as $key => $value) {
            $current = $settings->get($key);

            if ($current === $value) {
                continue;
            }

            $before[$key] = $current;
            $after[$key] = $value;
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $settings->flush();

        if ($after === []) {
            return false;
        }

        if (array_key_exists('vat_rate', $after)) {
            Cache::forget('menu.v1');
        }

        self::log($user, $section, $description, ['before' => $before, 'after' => $after]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(User $user, string $section, string $description, array $properties): void
    {
        ActivityLog::create([
            'event' => 'settings.'.$section,
            'user_id' => $user->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
