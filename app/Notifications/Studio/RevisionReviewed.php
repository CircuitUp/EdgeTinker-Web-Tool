<?php

namespace App\Notifications\Studio;

use App\Enums\RevisionAction;
use App\Enums\RevisionStatus;
use App\Models\Revision;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a creator that their submission was approved or rejected. */
class RevisionReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Revision $revision) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->revision->title;
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        if ($this->revision->status === RevisionStatus::Approved) {
            $mail->subject("Approved: $title")
                ->line($this->revision->action === RevisionAction::Delete
                    ? "Your request to delete “{$title}” was approved. It has been removed from the live site."
                    : "Your submission “{$title}” was approved and is now live.");

            if ($this->revision->comment) {
                $mail->line("Comment from the reviewer: {$this->revision->comment}");
            }

            return $mail->action('Open Content Studio', route('studio.dashboard'));
        }

        return $mail->subject("Changes requested: $title")
            ->line("Your submission “{$title}” was not approved yet.")
            ->line("Comment from the reviewer: {$this->revision->comment}")
            ->action('Open your submission', route('studio.revisions.show', $this->revision->id))
            ->line('You can edit it and submit it again.');
    }
}
