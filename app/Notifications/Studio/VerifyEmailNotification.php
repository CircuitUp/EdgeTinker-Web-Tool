<?php

namespace App\Notifications\Studio;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Signed link, valid for 24 hours.
        $url = URL::temporarySignedRoute('studio.verification.verify', now()->addHours(24), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject('Confirm your email address – EdgeTinker Content Studio')
            ->greeting("Hello {$notifiable->name},")
            ->line('Please confirm your email address to finish registering for EdgeTinker Content Studio.')
            ->action('Confirm email address', $url)
            ->line('After you confirm, the Content Admin will review and activate your account. You will receive an email when you can sign in.')
            ->line('This link expires in 24 hours. If you did not register, you can ignore this email.');
    }
}
