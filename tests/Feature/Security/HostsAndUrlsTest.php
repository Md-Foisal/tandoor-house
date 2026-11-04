<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

afterEach(function () {
    Request::setTrustedHosts([]);
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    TrustProxies::flushState();
});

it('refuses a forged Host header outside local development', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://evil.example/')->assertStatus(400);
});

it('serves APP_URL and its subdomains', function () {
    app()->detectEnvironment(fn () => 'production');
    $host = parse_url(config('app.url'), PHP_URL_HOST);

    $this->get("http://{$host}/")->assertOk();
    $this->get("http://www.{$host}/")->assertOk();
});

it('builds reset links from APP_URL, not the Host header', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('http://evil.example/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, rtrim(config('app.url'), '/').'/') && ! str_contains($url, 'evil.example');
    });
});

it('uses https links in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->app->getProvider(AppServiceProvider::class)->boot();

    expect(url('/menu'))->toStartWith('https://');
});

it('reads client IPs through the proxies named in TRUSTED_PROXIES only', function () {
    config(['security.trusted_proxies' => '10.0.0.1']);
    $this->app->getProvider(AppServiceProvider::class)->boot();

    $seen = null;
    Route::get('/__ip', function (Request $request) use (&$seen) {
        $seen = $request->ip();

        return 'ok';
    });

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/__ip')->assertOk();
    expect($seen)->toBe('203.0.113.9');

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/__ip')->assertOk();
    expect($seen)->toBe('198.51.100.7');
});

it('limits Reverb sockets to the app host by default', function () {
    expect(config('reverb.apps.apps.0.allowed_origins'))->toBe([parse_url(config('app.url'), PHP_URL_HOST)]);
});

it('keeps the private disk off the web', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse();
    $this->get('/storage/menu-originals/anything.jpg')->assertNotFound();
});
