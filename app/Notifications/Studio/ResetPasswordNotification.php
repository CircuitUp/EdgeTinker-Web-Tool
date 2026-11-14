<?php

namespace App\Notifications\Studio;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('studio.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your password – EdgeTinker Content Studio')
            ->greeting("Hello {$notifiable->name},")
            ->line('A password reset was requested for your EdgeTinker Content Studio account.')
            ->action('Choose a new password', $url)
            ->line("This link expires in $minutes minutes. If you did not expect this email, no action is needed and your password stays the same.");
    }
}
