<?php

namespace App\Notifications\Customer;

use App\Models\Reservation;

class CalendarInvite
{
    public static function for(Reservation $reservation): string
    {
        $name = (string) config('restaurant.name');
        $contact = config('restaurant.contact.address');
        $location = implode(', ', array_filter([$contact['line1'], $contact['city'], $contact['postcode']]));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$name.'//Bookings//EN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$reservation->reference}@".parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$reservation->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$reservation->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.self::escape(__('notify.booking.ics_summary', ['restaurant' => $name])),
            'LOCATION:'.self::escape($location),
            'DESCRIPTION:'.self::escape(__('notify.booking.ics_description', ['party' => $reservation->party_size, 'reference' => $reservation->reference])),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $value);
    }
}
