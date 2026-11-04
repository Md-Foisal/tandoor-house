<?php

return [

    /*
     * A public demo: production hardening stays on, but no real money, email
     * or text message can leave the app, and the shared accounts stay usable.
     */
    'enabled' => (bool) env('DEMO_MODE', false),

    'password' => 'password',

    // Shown on the sign-in page, one tap each.
    'accounts' => [
        'owner' => 'owner@tandoorhouse.test',
        'manager' => 'manager@tandoorhouse.test',
        'kitchen' => 'kitchen@tandoorhouse.test',
        'waiter' => 'waiter@tandoorhouse.test',
        'cashier' => 'cashier@tandoorhouse.test',
        'rider' => 'rider@tandoorhouse.test',
        'customer' => 'customer@tandoorhouse.test',
    ],

];
