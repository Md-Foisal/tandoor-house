<?php

return [
    'greeting' => 'Hello :name,',
    'order' => [
        'track' => 'Follow your order',
        'reference' => 'Order :number',
        'no_reason' => 'the kitchen cannot manage it right now',
        'placed' => [
            'subject' => 'We have your order :number',
            'headline' => 'Thank you, we have your order.',
            'body' => ':restaurant has order :number. We will let you know as soon as the kitchen accepts it.',
            'sms' => ':restaurant: we have your order :number and will confirm shortly.',
        ],
        'accepted' => [
            'subject' => 'Order :number is being prepared',
            'headline' => 'The kitchen has your order.',
            'body' => 'Order :number is accepted and should be ready around :time.',
            'sms' => ':restaurant: order :number is accepted, ready around :time.',
        ],
        'ready' => [
            'subject' => 'Order :number is ready to collect',
            'headline' => 'Your order is ready.',
            'body' => 'Order :number is packed and waiting for you at the counter. Quote the number when you arrive.',
            'sms' => ':restaurant: order :number is ready to collect.',
        ],
        'out_for_delivery' => [
            'subject' => 'Order :number is on its way',
            'headline' => 'Your order is on its way.',
            'body' => 'Your rider has left with order :number. Keep your phone close in case they need to find you.',
            'sms' => ':restaurant: order :number is out for delivery.',
        ],
        'delivered' => [
            'subject' => 'Order :number has arrived',
            'headline' => 'Delivered. Enjoy.',
            'body' => 'Order :number has been delivered. If anything is not right, reply to this email and we will sort it.',
            'sms' => ':restaurant: order :number has been delivered. Enjoy.',
        ],
        'rejected' => [
            'subject' => 'We could not take order :number',
            'headline' => 'Sorry, we cannot make this one.',
            'body' => 'We could not take order :number: :reason. Any payment is being returned to you in full.',
            'sms' => ':restaurant: sorry, we could not take order :number (:reason). Any payment is being refunded.',
        ],
        'cancelled' => [
            'subject' => 'Order :number is cancelled',
            'headline' => 'Your order is cancelled.',
            'body' => 'Order :number has been cancelled: :reason. Any payment is being returned to you.',
            'sms' => ':restaurant: order :number is cancelled (:reason). Any payment is being refunded.',
        ],
    ],
    'booking' => [
        'party' => '{1} Table for one|[2,*] Table for :count',
        'reference' => 'Reference :reference',
        'manage' => 'View or change your booking',
        'ics_summary' => 'Dinner at :restaurant',
        'ics_description' => 'Table for :party. Reference :reference.',
        'reminder' => [
            'subject' => 'See you at :when at :restaurant',
            'headline' => 'Your table is nearly ready.',
            'body' => 'A quick note that we are expecting you at :when for a table for :party. If you cannot make it, please let us know so we can free the table.',
            'sms' => ':restaurant: see you at :when, table for :party. Ref :reference.',
        ],
        'confirmed' => [
            'subject' => 'Your table at :restaurant is booked',
            'headline' => 'Your table is booked.',
            'body' => 'We have a table for :party waiting for you on :when. If your plans change, let us know so we can offer it to someone else.',
            'sms' => ':restaurant: table for :party, :when. Ref :reference.',
        ],
        'cancelled' => [
            'subject' => 'Your booking at :restaurant is cancelled',
            'headline' => 'Your booking is cancelled.',
            'body' => 'The table for :party on :when is cancelled. We hope to see you another time.',
            'sms' => ':restaurant: your booking for :when is cancelled. Ref :reference.',
        ],
    ],
    'tips' => [
        'paid' => 'The tips for the week starting :week are marked as paid, so they can no longer be changed.',
        'recorded' => ':user recorded the tips split for the week starting :week: :total shared between :people.',
    ],
];
