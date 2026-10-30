<?php

namespace App\Notifications\Staff;

use App\Actions\Team\InviteStatus;
use App\Models\User;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly string $token, public readonly string $invitedBy) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(User $user): string
    {
        return route('invite.show', ['token' => $this->token, 'email' => $user->email]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $restaurant = (string) config('restaurant.name');

        return (new MailMessage)
            ->subject(__('staff_team.invite.subject', ['restaurant' => $restaurant]))
            ->markdown('mail.staff-invite', [
                'name' => MarkdownText::escape(explode(' ', trim($notifiable->name))[0]),
                'restaurant' => MarkdownText::escape($restaurant),
                'role' => $notifiable->role->label(),
                'invitedBy' => MarkdownText::escape($this->invitedBy),
                'days' => InviteStatus::VALID_DAYS,
                'url' => $this->url($notifiable),
            ]);
    }
}
