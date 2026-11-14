<?php

namespace App\Notifications\Studio;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a creator that the admin activated (or suspended) their account. */
class AccountStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $activated) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        return $this->activated
            ? $mail->subject('Your Content Studio account is active')
                ->line('Your EdgeTinker Content Studio account has been activated. You can sign in now.')
                ->action('Sign in', route('studio.login'))
            : $mail->subject('Your Content Studio account has been suspended')
                ->line('Your EdgeTinker Content Studio account has been suspended and you can no longer sign in.')
                ->line('If you think this is a mistake, please contact the Content Admin.');
    }
}
