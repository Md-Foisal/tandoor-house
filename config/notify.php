<?php

return [

    /*
    | SMS and WhatsApp drivers. "log" writes messages to the sms log channel;
    | "null" sends nothing. Swap in a real provider by binding its sender.
    */
    'sms' => [
        'driver' => env('NOTIFY_SMS_DRIVER', 'log'),
        'log_channel' => 'sms',
        'max_segments' => 3,
    ],

    'whatsapp' => [
        'driver' => env('NOTIFY_WHATSAPP_DRIVER', 'null'),
    ],

    /*
    | Channels a kind of customer message may use, on top of the restaurant's
    | own notification settings. Kinds not listed may use every enabled channel.
    */
    'kinds' => [
        'review_request' => ['mail'],
        'abandoned_cart' => ['mail'],
    ],

];
