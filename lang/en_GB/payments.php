<?php

return [
    'already_paid' => 'This has already been paid. Nothing more to take.',
    'nothing_owed' => 'There is nothing to pay for this one.',
    'declined' => 'Your card was declined. Please try another card or pay at the restaurant.',
    'gateway_error' => 'We could not reach the payment provider. Please try again in a moment.',
    'describe' => [
        'order' => 'Order :number at :restaurant',
        'deposit' => 'Booking deposit :reference at :restaurant',
    ],
    'fake' => [
        'declined' => 'Test card declined: amounts ending in 51p are always declined.',
    ],
    'venue' => [
        'pending' => 'Pay when you collect or at your table.',
        'cash_refund' => 'Hand the cash back to the guest.',
        'not_venue' => 'Only pay-at-venue and cash payments can be taken by staff.',
        'not_pending' => 'This payment is no longer waiting to be taken.',
    ],
    'refund' => [
        'nothing_paid' => 'Nothing has been paid on this payment yet, so there is nothing to refund.',
        'too_much' => 'You can refund up to :max on this payment.',
        'gateway_failed' => 'The payment provider would not make this refund. Nothing has been changed.',
    ],
];
