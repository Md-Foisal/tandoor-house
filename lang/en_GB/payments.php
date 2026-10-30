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
    'panel' => [
        'title' => 'Pay :amount',
        'secure' => 'Card details go straight to the payment provider. We never see or keep them.',
        'loading' => 'Getting the payment form ready',
        'pay' => 'Pay :amount',
        'processing' => 'Taking payment',
        'success' => 'Paid. Taking you to your order.',
        'success_booking' => 'Deposit paid. Taking you to your booking.',
        'declined' => 'That payment did not go through. You have not been charged. Try again or use another card.',
        'error' => 'We could not complete that just now. You have not been charged. Please try again.',
        'pending' => 'Your bank is still confirming. Give it a moment, then check your order.',
        'offline' => "You're offline. Reconnect to pay; nothing has been charged.",
        'load_failed' => 'The payment form did not load. Check your connection and try again.',
        'retry' => 'Try again',
        'test_badge' => 'Test mode',
        'test_title' => 'Choose a test card',
        'test_note' => 'Nothing real is charged and no card number is collected. Totals ending in 51p are always declined.',
        'cards' => [
            'tok_visa' => 'Visa ending 4242',
            'tok_mastercard' => 'Mastercard ending 4444',
            'tok_decline' => 'Card that is declined',
        ],
    ],
];
