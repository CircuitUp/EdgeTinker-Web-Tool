<?php

namespace App\Notifications\Studio;

use App\Enums\RevisionAction;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the Content Admin that a creator has submitted something for review. */
class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Revision $revision) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = strtolower(app(ContentTypeRegistry::class)->get($this->revision->entity_type)->label());
        $author = $this->revision->author?->name ?? 'A creator';
        $what = match ($this->revision->action) {
            RevisionAction::Create => "a new $type",
            RevisionAction::Update => "changes to the $type",
            RevisionAction::Delete => "a request to delete the $type",
        };

        return (new MailMessage)
            ->subject("For review: {$this->revision->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("$author submitted $what “{$this->revision->title}”.")
            ->action('Review it', route('studio.review.show', $this->revision->id))
            ->line('Nothing changes on the live site until you approve it.');
    }
}
