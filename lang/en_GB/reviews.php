<?php

return [
    'title' => 'Your reviews',
    'meta_description' => 'Tell us how your order went, and see what you have already said.',
    'intro' => 'Tell us how it went. We read every one, and the kitchen hears about it.',
    'loading' => 'Loading your reviews',

    'waiting' => [
        'title' => 'Waiting for your review',
        'count' => ':count order|:count orders',
        'order' => 'Order :number',
        'items_label' => 'What you ordered',
        'empty_title' => 'Nothing waiting.',
        'empty_body' => 'When an order is completed it appears here for 60 days, ready for a few words.',
        'empty_action' => 'See the menu',
    ],

    'form' => [
        'rating_legend' => 'How was it?',
        'rating_option' => ':count out of 5, :label',
        'rating_labels' => [
            '1' => 'Poor',
            '2' => 'Fair',
            '3' => 'Good',
            '4' => 'Very good',
            '5' => 'Outstanding',
        ],
        'body' => 'Your review',
        'body_hint' => 'At least 20 characters. What was good, what was not.',
        'body_count' => ':count of 1000',
        'already' => 'You have already reviewed this order.',
        'area' => 'Your neighbourhood (optional)',
        'area_hint' => 'Shown beside your first name and initial, like “Priya S., Bow”.',
        'submit' => 'Send review',
        'sending' => 'Sending',
        'offline' => 'You are offline. Sending needs a connection.',
        'moderation' => 'We read each review before it goes on the site.',
    ],

    'mine' => [
        'title' => 'Your reviews',
        'published' => 'On the site',
        'pending' => 'Awaiting moderation',
        'reply' => 'Reply from :name',
        'reply_from' => 'The restaurant',
        'for_order' => 'Order :number',
        'empty_title' => 'No reviews yet.',
        'empty_body' => 'Once you have written one, it lands here like a note in the guest book.',
    ],

    'rating' => ':rating out of 5',

    'flash' => [
        'sent' => 'Thank you. Your review is with us and will go up once we have read it.',
    ],

    'errors' => [
        'rating' => 'Choose a rating from 1 to 5.',
        'body_short' => 'Write at least 20 characters so we know what you meant.',
        'body_long' => 'Keep it to 1000 characters.',
        'area' => 'Keep the neighbourhood to 60 characters.',
    ],
];
