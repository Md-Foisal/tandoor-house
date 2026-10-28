<?php

namespace App\Providers;

use App\Enums\StaffArea;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureStaffAreas();
    }

    /**
     * One gate per staff panel section, e.g. "staff.kitchen".
     */
    protected function configureStaffAreas(): void
    {
        foreach (StaffArea::cases() as $area) {
            Gate::define("staff.{$area->value}", fn (User $user) => $area->allows($user->role));
        }
    }

    /**
     * Limits for public endpoints that write: keyed by session where there is
     * one, so a busy shared IP (an office, a phone network) is not punished.
     */
    protected function configureRateLimiting(): void
    {
        $key = fn (Request $request): string => $request->hasSession()
            ? $request->session()->getId()
            : (string) $request->ip();

        RateLimiter::for('basket', fn (Request $request) => Limit::perMinute(60)->by($key($request)));
        RateLimiter::for('promo', fn (Request $request) => [
            Limit::perMinute(10)->by($key($request)),
            Limit::perHour(60)->by((string) $request->ip()),
        ]);
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($key($request)));
        RateLimiter::for('booking', fn (Request $request) => [
            Limit::perMinute(30)->by($key($request)),
            Limit::perHour(20)->by((string) $request->ip())->response(
                fn () => back()->withErrors(['booking' => __('booking.too_many')]),
            ),
        ]);
        // Runs before route model binding, so the table is still its QR token.
        RateLimiter::for('table', fn (Request $request) => Limit::perMinute(12)->by(
            $request->route()?->originalParameter('table').'|'.$key($request),
        ));
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        JsonResource::withoutWrapping();

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
