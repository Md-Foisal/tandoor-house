<?php

namespace App\Notifications;

use App\Actions\UkFormats;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class PhoneNumber
{
    /**
     * UK mobiles in any common spelling, or an international number already
     * in E.164, become "+447700900123"; anything else is null.
     */
    public static function e164(?string $value): ?string
    {
        $value = str_replace('(0)', '', (string) $value);
        $uk = UkFormats::mobile($value);

        if ($uk !== null) {
            return $uk;
        }

        $compact = (string) preg_replace('/[\s\-().]/', '', (string) $value);

        return preg_match('/^\+[1-9]\d{7,14}$/', $compact) === 1 ? $compact : null;
    }

    public static function routeFor(object $notifiable, string $channel, Notification $notification): ?string
    {
        $route = $notifiable instanceof AnonymousNotifiable || $notifiable instanceof User
            ? $notifiable->routeNotificationFor($channel, $notification)
            : null;

        if ($route === null && isset($notifiable->phone)) {
            $route = $notifiable->phone;
        }

        return self::e164(is_string($route) ? $route : null);
    }
}
