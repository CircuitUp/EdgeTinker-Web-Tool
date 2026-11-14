<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emails a contact-form message to the company inbox. Replying goes straight to the visitor. */
class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message;

        // Strip line breaks from anything that goes into an email header.
        $clean = fn (?string $value) => trim(preg_replace('/[\r\n]+/', ' ', (string) $value));

        // Visitor text shown in the email body is HTML-escaped as well.
        $text = fn (?string $value) => e($clean($value));

        $mail = (new MailMessage)
            ->subject('Website enquiry: '.$clean($message->subject))
            ->replyTo($message->email, $clean($message->name))
            ->greeting('New message from the website contact form')
            ->line('**Name:** '.$text($message->name))
            ->line('**Email:** '.$text($message->email));

        if ($message->company) {
            $mail->line('**Company:** '.$text($message->company));
        }

        $mail->line('**Subject:** '.$text($message->subject))->line('**Message:**');

        foreach (preg_split('/\n{2,}/', trim($message->message)) as $paragraph) {
            $mail->line(e($paragraph));
        }

        return $mail->line('Reply to this email to answer '.$text($message->name).' directly. A copy is kept in Content Studio under Messages.');
    }
}
