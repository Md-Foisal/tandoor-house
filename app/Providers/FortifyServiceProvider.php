<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->throttleAccountRoutes();
    }

    /**
     * Fortify only throttles login, two-factor and passkeys; these routes
     * send email or check a password, so they get a limiter too.
     */
    private function throttleAccountRoutes(): void
    {
        $this->app->booted(function () {
            $limiters = [
                'register.store' => config('fortify.limiters.register'),
                'password.email' => config('fortify.limiters.forgot-password'),
                'password.update' => config('fortify.limiters.reset-password'),
                'password.confirm.store' => config('fortify.limiters.confirm-password'),
            ];

            foreach (Route::getRoutes()->getRoutes() as $route) {
                $limiter = $limiters[$route->getName()] ?? null;

                if (is_string($limiter) && $limiter !== '' && ! in_array('throttle:'.$limiter, $route->middleware(), true)) {
                    $route->middleware('throttle:'.$limiter);
                }
            }
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        $emailAndIp = fn (Request $request): string => Str::transliterate(Str::lower((string) $request->input(Fortify::email())).'|'.$request->ip());

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('forgot-password', fn (Request $request) => [
            Limit::perMinute(5)->by($emailAndIp($request)),
            Limit::perHour(30)->by('ip|'.$request->ip()),
        ]);
        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(5)->by($emailAndIp($request)));
        RateLimiter::for('confirm-password', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->user()?->getAuthIdentifier() ?? '').'|'.$request->ip(),
        ));

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
