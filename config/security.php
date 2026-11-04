<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs or CIDR ranges of the load balancer or CDN in front
    | of the app, so client IPs (rate limits, logs) and https are read from
    | X-Forwarded-* headers. "*" trusts the immediate hop. Empty trusts none.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
