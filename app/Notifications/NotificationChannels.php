<?php

namespace App\Notifications;

use App\Support\Settings;

class NotificationChannels
{
    /**
     * Channels to use for a kind of customer message, following the
     * restaurant's notification settings. WhatsApp only appears once a real
     * sender replaces the null driver.
     *
     * @return list<string>
     */
    public static function for(string $kind): array
    {
        $stored = (array) app(Settings::class)->get('notification_channels', []);

        $enabled = array_values(array_filter(
            ['mail', 'sms', 'whatsapp'],
            fn (string $channel) => match ($channel) {
                'whatsapp' => (bool) ($stored['whatsapp'] ?? false) && config('notify.whatsapp.driver') !== 'null',
                default => (bool) ($stored[$channel] ?? true),
            },
        ));

        $allowed = config("notify.kinds.{$kind}");

        return is_array($allowed) ? array_values(array_intersect($enabled, $allowed)) : $enabled;
    }
}
