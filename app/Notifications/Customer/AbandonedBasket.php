<?php

namespace App\Notifications\Customer;

use App\Models\User;
use App\Notifications\NotificationChannels;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbandonedBasket extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $count) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return in_array('mail', NotificationChannels::for('abandoned_basket'), true) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('automations.customers.abandoned_basket.subject', ['restaurant' => config('restaurant.name')]))
            ->markdown('mail.abandoned-basket', [
                'name' => MarkdownText::escape(explode(' ', trim($notifiable instanceof User ? $notifiable->name : ''))[0]),
                'headline' => __('automations.customers.abandoned_basket.headline'),
                'body' => __('automations.customers.abandoned_basket.body'),
                'count' => $this->count,
                'url' => route('basket'),
            ]);
    }
}
