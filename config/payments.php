<?php

use App\Payments\Gateways\FakeGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\Gateways\VenueGateway;

// A public demo must never reach a real payment provider, whatever keys are set.
$online = env('STRIPE_SECRET') && ! env('DEMO_MODE') ? 'stripe' : 'fake';

return [

    'currency' => 'gbp',

    /*
     * Which driver takes each payment method. Online methods use Stripe only
     * when STRIPE_SECRET is set, and the fake gateway otherwise (it refuses
     * to run in production).
     */
    'methods' => [
        'card' => $online,
        'apple_pay' => $online,
        'google_pay' => $online,
        'pay_at_venue' => 'venue',
        'cash' => 'venue',
    ],

    'drivers' => [
        'fake' => FakeGateway::class,
        'venue' => VenueGateway::class,
        'stripe' => StripeGateway::class,
    ],

    'fake' => [
        // Card amounts whose pence end in this value are declined.
        'decline_pence' => 51,
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Live keys take real money, so they work only when this is set on purpose at launch.
        'allow_live' => (bool) env('STRIPE_ALLOW_LIVE', false),
        'api' => 'https://api.stripe.com/v1',
        'tolerance' => 300,
    ],

];
