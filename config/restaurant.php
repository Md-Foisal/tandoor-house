<?php

return [

    /*
    | The trading name shown everywhere: page titles, emails, receipts.
    */
    'name' => env('RESTAURANT_NAME', 'Tandoor House'),

    /*
    | Drives seed data and default copy. Supported: "modern-indian".
    */
    'cuisine' => env('RESTAURANT_CUISINE', 'modern-indian'),

    'currency' => 'GBP',

    'contact' => [
        'phone' => env('RESTAURANT_PHONE', '020 7946 0321'),
        'email' => env('RESTAURANT_EMAIL', 'hello@tandoorhouse.test'),
        'address' => [
            'line1' => env('RESTAURANT_ADDRESS_LINE1', '14 Hanbury Street'),
            'city' => env('RESTAURANT_CITY', 'London'),
            'postcode' => env('RESTAURANT_POSTCODE', 'E1 6QR'),
        ],
        'latitude' => (float) env('RESTAURANT_LAT', 51.5206),
        'longitude' => (float) env('RESTAURANT_LNG', -0.0726),
    ],

    'company' => [
        'legal_name' => env('RESTAURANT_LEGAL_NAME', 'Tandoor House Ltd'),
        'vat_number' => env('RESTAURANT_VAT_NUMBER', 'GB 123 4567 89'),
        'company_number' => env('RESTAURANT_COMPANY_NUMBER', '01234567'),
    ],

];
