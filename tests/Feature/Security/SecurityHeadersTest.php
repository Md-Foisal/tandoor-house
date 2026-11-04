<?php

use Illuminate\Support\Facades\Vite;

function cspDirectives(string $policy): array
{
    return collect(explode(';', $policy))
        ->map(fn ($part) => preg_split('/\s+/', trim($part)))
        ->filter(fn ($parts) => $parts[0] !== '')
        ->mapWithKeys(fn ($parts) => [array_shift($parts) => $parts])
        ->all();
}

it('sends the hardening headers on a page', function () {
    $response = $this->get('/')->assertOk();

    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://js.stripe.com")')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('locks the page to its own origin, Stripe and Reverb', function () {
    // The policy adds the Vite origin while `npm run dev` is running.
    Vite::useHotFile(storage_path('framework/testing/no-hot-file'));

    config([
        'broadcasting.connections.reverb.options.host' => 'ws.example.test',
        'broadcasting.connections.reverb.options.port' => 443,
        'broadcasting.connections.reverb.options.scheme' => 'https',
    ]);

    $csp = cspDirectives($this->get('/')->headers->get('Content-Security-Policy'));

    expect($csp['default-src'])->toBe(["'self'"])
        ->and($csp['script-src'])->toContain("'self'", 'https://js.stripe.com')
        ->and($csp['script-src'])->not->toContain("'unsafe-inline'")
        ->and($csp['script-src'])->not->toContain("'unsafe-eval'")
        ->and($csp['style-src'])->toBe(["'self'", "'unsafe-inline'"])
        ->and($csp['img-src'])->toBe(["'self'", 'data:', 'blob:', 'https://*.stripe.com'])
        ->and($csp['connect-src'])->toBe(["'self'", 'wss://ws.example.test:443', 'https://api.stripe.com'])
        ->and($csp['frame-src'])->toContain('https://js.stripe.com', 'https://hooks.stripe.com')
        ->and($csp['frame-ancestors'])->toBe(["'none'"])
        ->and($csp['object-src'])->toBe(["'none'"])
        ->and($csp['base-uri'])->toBe(["'self'"])
        ->and($csp['form-action'])->toBe(["'self'"]);
});

it('puts a fresh nonce on every inline script, matching the policy', function () {
    $first = $this->get('/');
    preg_match("/'nonce-([^']+)'/", $first->headers->get('Content-Security-Policy'), $match);
    $nonce = $match[1];

    $html = $first->getContent();
    preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/', $html, $inline);

    expect($inline[0])->not->toBeEmpty();

    foreach ($inline[0] as $tag) {
        expect($tag)->toContain("nonce=\"{$nonce}\"");
    }

    expect($html)->toContain('<script type="application/ld+json" nonce="'.$nonce.'"');

    preg_match("/'nonce-([^']+)'/", $this->get('/')->headers->get('Content-Security-Policy'), $second);
    expect($second[1])->not->toBe($nonce);
});

it('lets the Vite dev server in only while it is running', function () {
    $hot = tempnam(sys_get_temp_dir(), 'hot');
    file_put_contents($hot, 'http://[::1]:5173');
    Vite::useHotFile($hot);

    $csp = cspDirectives($this->get('/')->headers->get('Content-Security-Policy'));

    expect($csp['script-src'])->toContain('http://[::1]:5173')
        ->and($csp['style-src'])->toContain('http://[::1]:5173')
        ->and($csp['connect-src'])->toContain('http://[::1]:5173', 'ws://[::1]:5173');

    unlink($hot);
});

it('adds HSTS in production only', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('allows only the printable receipt button as an inline handler', function () {
    $csp = cspDirectives($this->get('/')->headers->get('Content-Security-Policy'));

    expect($csp['script-src'])->toContain("'unsafe-hashes'", "'sha256-".base64_encode(hash('sha256', 'window.print()', true))."'");
});
