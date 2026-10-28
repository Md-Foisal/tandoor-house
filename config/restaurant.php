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

    /*
    | Defaults for settings the owner can change in the staff panel.
    | Opening hours are per ISO weekday (1 = Monday), local time.
    */
    'defaults' => [
        'opening_hours' => [
            1 => [],
            2 => [['12:00', '15:00'], ['17:30', '22:30']],
            3 => [['12:00', '15:00'], ['17:30', '22:30']],
            4 => [['12:00', '15:00'], ['17:30', '22:30']],
            5 => [['12:00', '23:00']],
            6 => [['12:00', '23:00']],
            7 => [['12:00', '21:30']],
        ],
        'bank_holidays' => [],
        'vat_rate' => 2000,
        'service_charge_rate' => 1250,
        'hygiene_rating' => 5,
        'opened_year' => 2016,
        // Tip choices as basis points of the bill: none, 10%, 12.5%, 15%.
        'tip_presets' => [0, 1000, 1250, 1500],
        'pickup_slot_minutes' => 15,
        'pickup_lead_minutes' => 20,
        'reservation' => [
            'slot_minutes' => 15,
            'duration_minutes' => 105,
            'deposit_from_party' => 6,
            'deposit_per_head' => 1000,
            'max_party' => 10,
            'no_show_minutes' => 20,
        ],
        'loyalty' => [
            'points_per_pound' => 1,
            'reward_points' => 150,
            'reward_value' => 1000,
        ],
        'automations' => [
            'auto_accept' => true,
            'notifications' => true,
            'stock_deduction' => true,
            'reservation_reminders' => true,
            'no_show_release' => true,
            'daily_close' => true,
            'weekly_report' => true,
            'review_requests' => true,
            'loyalty' => true,
            'abandoned_cart' => true,
        ],
    ],

];
