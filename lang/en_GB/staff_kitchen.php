<?php

return [
    'title' => 'Kitchen display',
    'stations' => [
        'all' => 'All',
        'label' => 'Station',
    ],
    'ticket' => [
        'delivery' => 'Delivery',
        'pickup_at' => 'Pickup :time',
        'waiting' => 'Waiting :minutes min',
        'bump_line' => 'Mark :dish as plated',
        'bump_ticket' => 'Bump ticket',
        'bump_station' => 'Bump :station',
        'order_note' => 'Order note',
        'allergy' => 'Allergy',
        'key' => 'Press :key to select',
        'selected' => 'Selected ticket',
        'plated' => 'Plated',
        'offline_reason' => "You're offline. Bumping is paused until the connection returns.",
    ],
    'all_day' => [
        'title' => 'All day',
        'description' => 'Everything still to cook on the rail.',
        'empty' => 'Nothing left to cook.',
        'item' => ':quantity× :dish',
        'sold_out' => 'Sold out',
        'already_sold_out' => 'Already off the menu',
    ],
    'sold_out' => [
        'title' => 'Mark :dish as sold out?',
        'body' => 'Guests will no longer be able to order it online or at the table until a manager puts it back on the menu.',
        'confirm' => 'Mark as sold out',
        'cancel' => 'Keep it on',
    ],
    'sold_out_done' => ':dish is now sold out.',
    'recent' => [
        'title' => 'Recently bumped',
        'empty' => 'Nothing bumped in the last 10 minutes.',
        'recall' => 'Recall',
        'recall_label' => 'Recall :dish from :number',
        'last' => 'Last bumped',
    ],
    'theme' => [
        'light' => 'Switch to light display',
        'dark' => 'Switch to dark display',
    ],
    'clock' => 'Time now',
    'empty' => [
        'title' => 'Nothing on the rail.',
        'body' => 'Enjoy the quiet.',
        'station_body' => 'Nothing for :station right now. Enjoy the quiet.',
    ],
    'error' => [
        'title' => "The rail couldn't refresh.",
        'body' => "Tickets may be out of date. We'll keep trying.",
        'retry' => 'Refresh now',
    ],
    'offline' => 'Offline. Tickets may be out of date and bumping is paused.',
    'bump_failed' => "That ticket couldn't be bumped. It may have already left the rail.",
    'shortcuts' => [
        'title' => 'Keyboard',
        'select' => '1–9 select a ticket',
        'bump' => 'Enter bumps it',
        'recall' => 'R recalls the last bumped',
    ],
    'log' => [
        'sold_out' => ':name marked :dish as sold out from the kitchen display.',
    ],
];
