<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening for every web response: a nonce-based Content Security
 * Policy (Stripe and Reverb allowed by name), plus the usual framing,
 * sniffing, referrer and feature headers. HSTS is production only so local
 * http:// hosts keep working.
 */
class SecurityHeaders
{
    private const STRIPE_SCRIPTS = ['https://js.stripe.com', 'https://*.js.stripe.com'];

    /**
     * The printable receipt's `onclick="window.print()"` button, allowed by
     * hash so no other inline handler runs.
     */
    private const PRINT_HANDLER = "'sha256-MguIPR6qNR8D3B+eAlK+bIRTZe8t3wkOY4B/56Me9FU='";

    /**
     * Laravel Herd injects an inline early-error hook into local pages after
     * the response is built, so it cannot carry the nonce. Allowed locally only.
     */
    private const HERD_EARLY_ERRORS = "'sha256-e9EdJBA/M3o5ruBrc1LqUrOz1yuPiJZlX/SG34Efiwg='";

    private const STRIPE_FRAMES = ['https://js.stripe.com', 'https://*.js.stripe.com', 'https://hooks.stripe.com'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(self "https://js.stripe.com")',
        ];

        // A PDF or download has no scripts to protect, and a strict policy can
        // stop the browser's own viewer from rendering it.
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html') || ! $response->headers->has('Content-Type')) {
            $headers['Content-Security-Policy'] = $this->policy($nonce);
        }

        if (app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    public function policy(string $nonce): string
    {
        $dev = $this->devServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", "'unsafe-hashes'", self::PRINT_HANDLER, ...(app()->isLocal() ? [self::HERD_EARLY_ERRORS] : []), ...self::STRIPE_SCRIPTS, ...$dev['http']],
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev['http']],
            'img-src' => ["'self'", 'data:', 'blob:', 'https://*.stripe.com'],
            'font-src' => ["'self'", ...$dev['http']],
            'connect-src' => ["'self'", ...$this->websocket(), 'https://api.stripe.com', ...$dev['http'], ...$dev['ws']],
            'frame-src' => self::STRIPE_FRAMES,
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
            'manifest-src' => ["'self'"],
            'worker-src' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    /**
     * The Reverb endpoint the browser opens for live updates.
     *
     * @return list<string>
     */
    private function websocket(): array
    {
        $host = config('broadcasting.connections.reverb.options.host');

        if (! is_string($host) || $host === '') {
            return [];
        }

        $secure = config('broadcasting.connections.reverb.options.scheme') === 'https';
        $port = config('broadcasting.connections.reverb.options.port');
        $suffix = $port ? ":{$port}" : '';

        return [($secure ? 'wss://' : 'ws://').$host.$suffix];
    }

    /**
     * The Vite dev server origin while `npm run dev` is running, for its
     * scripts, styles and hot-reload socket.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function devServer(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $origin = rtrim(trim((string) File::get(Vite::hotFile())), '/');
        $parts = parse_url($origin);

        if (! isset($parts['scheme'], $parts['host'])) {
            return ['http' => [], 'ws' => []];
        }

        $hostPort = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $ws = ($parts['scheme'] === 'https' ? 'wss://' : 'ws://').$hostPort;

        return ['http' => [$parts['scheme'].'://'.$hostPort], 'ws' => [$ws]];
    }
}
