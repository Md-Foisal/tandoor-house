<?php

/*
| Every cookie and browser-storage key the app sets. The cookie policy page is
| built from this list, so add a row here whenever something new is stored.
| Wording for each id lives in lang/en_GB/legal.php under cookies.items.<id>.
|
| category: "necessary" (needs no consent) or "preferences" (only with consent).
| storage:  cookie, local or session.
| duration: session_lifetime | browser_session | until_cleared
|           | ['minutes' => n] | ['days' => n]
| requires: optional config key that must be set for the row to be shown.
*/
return [

    // Shown as "Last updated" on the privacy notice and cookie policy.
    'policy_updated' => '2026-10-30',

    'consent' => [
        'name' => 'cookie_consent',
        // Bump to ask everyone again, for example when a new category is added.
        'version' => 1,
        'days' => 365,
    ],

    'items' => [
        [
            'id' => 'session',
            'name' => null, // the session cookie name comes from config('session.cookie')
            'category' => 'necessary',
            'storage' => 'cookie',
            'duration' => 'session_lifetime',
        ],
        [
            'id' => 'xsrf',
            'name' => 'XSRF-TOKEN',
            'category' => 'necessary',
            'storage' => 'cookie',
            'duration' => 'session_lifetime',
        ],
        [
            'id' => 'remember',
            'name' => 'remember_web_*',
            'category' => 'necessary',
            'storage' => 'cookie',
            // Laravel's default remember-me lifetime: 576,000 minutes.
            'duration' => ['days' => 400],
        ],
        [
            'id' => 'consent',
            'name' => 'cookie_consent',
            'category' => 'necessary',
            'storage' => 'cookie',
            'duration' => ['days' => 365],
        ],
        [
            'id' => 'menu_search',
            'name' => 'th.menu.query',
            'category' => 'necessary',
            'storage' => 'session',
            'duration' => 'browser_session',
        ],
        [
            'id' => 'stripe_mid',
            'name' => '__stripe_mid',
            'category' => 'necessary',
            'storage' => 'cookie',
            'duration' => ['days' => 365],
            'requires' => 'payments.stripe.key',
        ],
        [
            'id' => 'stripe_sid',
            'name' => '__stripe_sid',
            'category' => 'necessary',
            'storage' => 'cookie',
            'duration' => ['minutes' => 30],
            'requires' => 'payments.stripe.key',
        ],
        [
            'id' => 'appearance',
            'name' => 'appearance',
            'category' => 'preferences',
            'storage' => 'cookie',
            'duration' => ['days' => 365],
        ],
        [
            'id' => 'appearance_local',
            'name' => 'appearance',
            'category' => 'preferences',
            'storage' => 'local',
            'duration' => 'until_cleared',
        ],
        [
            'id' => 'sidebar',
            'name' => 'sidebar_state',
            'category' => 'preferences',
            'storage' => 'cookie',
            'duration' => ['days' => 7],
        ],
        [
            'id' => 'staff_device',
            'name' => 'kitchen-theme, kitchen-station, th-orders-chime',
            'category' => 'preferences',
            'storage' => 'local',
            'duration' => 'until_cleared',
        ],
    ],

];
