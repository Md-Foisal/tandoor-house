<?php

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Sms\LogSmsSender;
use App\Notifications\Sms\SmsSender;
use App\Notifications\WhatsApp\NullWhatsAppSender;
use App\Notifications\WhatsApp\WhatsAppSender;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => new LogSmsSender((string) config('notify.sms.log_channel', 'sms')));

        $this->app->bind(WhatsAppSender::class, fn () => new NullWhatsAppSender);
    }

    public function boot(): void
    {
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));
        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));
    }
}
