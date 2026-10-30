<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\MenuController;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Saves one section's settings and logs what changed (before and after) in
 * one transaction, then flushes the settings cache. Returns false when
 * nothing actually changed.
 */
class SettingsChange
{
    /**
     * @param  array<string, mixed>  $values  setting key => new value
     * @param  string  $description  already translated
     */
    public static function save(User $user, string $section, array $values, string $description): bool
    {
        $settings = app(Settings::class);

        $changed = DB::transaction(function () use ($settings, $user, $section, $values, $description) {
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

            if ($after === []) {
                return null;
            }

            app(ActivityRecorder::class)->record('settings.'.$section, $description, null, ['before' => $before, 'after' => $after], $user);

            return $after;
        });

        $settings->flush();

        if ($changed === null) {
            return false;
        }

        if (array_key_exists('vat_rate', $changed)) {
            Cache::forget(MenuController::CACHE_KEY);
        }

        return true;
    }
}
