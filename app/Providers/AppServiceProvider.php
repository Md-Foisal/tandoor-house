<?php

namespace App\Providers;

use App\Enums\StaffArea;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureUrls();
        $this->configureRateLimiting();
        $this->configureStaffAreas();
    }

    /**
     * Links in emails and redirects are built from APP_URL, never from the
     * request's Host header, and are https in production.
     */
    protected function configureUrls(): void
    {
        $proxies = trim((string) config('security.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
        }

        if (app()->isLocal()) {
            return;
        }

        $root = (string) config('app.url');

        if ($root !== '') {
            URL::forceRootUrl($root);
        }

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
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
     * one, so a busy shared IP (an office, a phone network) is not punished,
     * with a generous hourly cap per IP so fresh sessions cannot dodge it.
     */
    protected function configureRateLimiting(): void
    {
        $key = fn (Request $request): string => $request->hasSession()
            ? $request->session()->getId()
            : (string) $request->ip();

        RateLimiter::for('basket', fn (Request $request) => [
            Limit::perMinute(60)->by($key($request)),
            Limit::perHour(600)->by('basket-ip|'.$request->ip()),
        ]);
        RateLimiter::for('promo', fn (Request $request) => [
            Limit::perMinute(10)->by($key($request)),
            Limit::perHour(60)->by((string) $request->ip()),
        ]);
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(10)->by($key($request)),
            Limit::perHour(120)->by('checkout-ip|'.$request->ip()),
        ]);
        RateLimiter::for('booking', fn (Request $request) => [
            Limit::perMinute(30)->by($key($request)),
            Limit::perHour(20)->by((string) $request->ip())->response(
                fn () => back()->withErrors(['booking' => __('booking.too_many')]),
            ),
        ]);
        // Runs before route model binding, so the table is still its QR token.
        // Guests on the restaurant wifi share one IP, so its cap is high.
        RateLimiter::for('table', fn (Request $request) => [
            Limit::perMinute(12)->by($request->route()?->originalParameter('table').'|'.$key($request)),
            Limit::perHour(300)->by('table-ip|'.$request->ip()),
        ]);
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
        RateLimiter::for('staff-sensitive', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
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
