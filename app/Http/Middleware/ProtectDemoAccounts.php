<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everyone shares the demo accounts, so nobody may lock the next visitor out
 * of one: credentials, second factors and staff records are read-only.
 */
class ProtectDemoAccounts
{
    private const PROTECTED_PATHS = [
        'settings/password',
        'settings/profile',
        'user/two-factor-authentication',
        'user/confirmed-two-factor-authentication',
        'user/two-factor-recovery-codes',
        'user/passkeys',
        'user/passkeys/*',
        'staff/team/people/*',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            config('demo.enabled')
            && ! $request->isMethodSafe()
            && $request->is(self::PROTECTED_PATHS)
        ) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('ui.demo.protected'),
            ]);

            return back(303);
        }

        return $next($request);
    }
}
