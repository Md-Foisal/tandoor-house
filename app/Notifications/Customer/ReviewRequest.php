<?php

namespace App\Notifications\Customer;

use App\Models\Order;
use App\Models\User;
use App\Notifications\NotificationChannels;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return in_array('mail', NotificationChannels::for('review_request'), true) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['number' => $this->order->number, 'restaurant' => config('restaurant.name')];

        return (new MailMessage)
            ->subject(__('automations.customers.review_request.subject', $replace))
            ->markdown('mail.review-request', [
                'name' => MarkdownText::escape(explode(' ', trim($notifiable instanceof User ? $notifiable->name : ''))[0]),
                'headline' => __('automations.customers.review_request.headline'),
                'body' => __('automations.customers.review_request.body', $replace),
                'url' => route('account.reviews.index'),
                'order' => $this->order,
            ]);
    }
}
