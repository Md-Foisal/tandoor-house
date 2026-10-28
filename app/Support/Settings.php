<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Restaurant settings stored in the database, cached for the request
 * cycle and in the cache store, with defaults from config/restaurant.php.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return Arr::get(config('restaurant.defaults', []), $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    public function enabled(string $automation): bool
    {
        return (bool) Arr::get((array) $this->get('automations', []), $automation, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }
}
