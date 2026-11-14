<?php

namespace App\Notifications\Studio;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the Content Admin that a new creator has verified their email and is waiting to be activated. */
class CreatorAwaitingActivation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $creator) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Content Creator waiting for activation: {$this->creator->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->creator->name} ({$this->creator->email}) registered for Content Studio and confirmed their email address.")
            ->line('They cannot sign in until you activate the account.')
            ->action('Review users', route('studio.users.index'));
    }
}
