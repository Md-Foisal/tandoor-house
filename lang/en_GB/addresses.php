<?php

return [
    'title' => 'Saved addresses',
    'meta_description' => 'Keep the places you order to, with the door code and where to leave the bag.',
    'intro' => 'Where we bring it. Pick one at checkout, or add a new one on the way.',
    'count' => ':count of :max saved',
    'add' => 'Add an address',
    'add_aria' => 'Add a new address',
    'default' => 'Default',
    'make_default' => 'Make default',
    'make_default_aria' => 'Make :label your default address',
    'edit' => 'Edit',
    'edit_aria' => 'Edit :label',
    'delete' => 'Remove',
    'delete_aria' => 'Remove :label',
    'instructions' => 'Note for the rider',
    'limit_reached' => 'That is ten addresses. Remove one to add another.',
    'loading' => 'Loading your addresses',

    'zone' => [
        'inside' => 'We deliver here · :fee',
        'free' => 'free delivery',
        'outside' => 'Outside our delivery area — pickup only',
    ],

    'empty' => [
        'title' => 'No addresses saved yet.',
        'body' => 'Save your home or work and checkout fills it in for you.',
    ],

    'form' => [
        'add_title' => 'Add an address',
        'edit_title' => 'Edit :label',
        'description' => 'Delivery is to the address exactly as written here.',
        'label' => 'Name for this address',
        'label_hint' => 'Home, Work, Mum\'s',
        'line1' => 'Address line 1',
        'line2' => 'Address line 2 (optional)',
        'city' => 'Town or city',
        'postcode' => 'Postcode',
        'postcode_hint' => 'For example E2 7DJ',
        'instructions' => 'Delivery instructions (optional)',
        'instructions_hint' => 'Door code, which bell, where to leave it.',
        'default' => 'Use as my default address',
        'default_first' => 'Your first address is always the default.',
        'default_current' => 'This is your default. Make another one default to change it.',
        'save' => 'Save address',
        'saving' => 'Saving',
        'cancel' => 'Cancel',
        'offline' => 'You are offline. Saving needs a connection.',
    ],

    'confirm' => [
        'title' => 'Remove :label?',
        'body' => ':address will be taken off your account. Orders you have already placed are not affected.',
        'default_note' => 'It is your default, so your next oldest address becomes the default.',
        'confirm' => 'Remove address',
        'removing' => 'Removing',
        'cancel' => 'Keep it',
    ],

    'flash' => [
        'added' => 'Address saved.',
        'updated' => 'Address updated.',
        'removed' => 'Address removed.',
    ],

    'errors' => [
        'label' => 'Give this address a name, like Home.',
        'line1' => 'Add the first line of the address.',
        'city' => 'Add the town or city.',
        'postcode' => 'Enter a UK postcode, like E2 7DJ.',
        'instructions' => 'Keep the instructions to 200 characters.',
        'limit' => 'You can save up to :max addresses. Remove one to add another.',
    ],
];
