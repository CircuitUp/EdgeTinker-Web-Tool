<?php

namespace App\Livewire\Studio\Messages;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\ContactMessage;
use App\Studio\Audit;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Messages sent through the public contact form. Content Admin only: they contain visitors' personal details. */
#[Title('Messages')]
class Index extends Component
{
    use StudioScreen, WithPagination;

    /** all | unread */
    #[Url]
    public string $show = 'all';

    #[Url(as: 'message')]
    public ?int $openId = null;

    public function updatingShow(): void
    {
        $this->resetPage();
    }

    public function open(int $messageId): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        $message = ContactMessage::findOrFail($messageId);
        $this->openId = $message->id;

        if ($message->read_at === null) {
            $message->update(['read_at' => now()]);
        }
    }

    public function markUnread(int $messageId): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        ContactMessage::findOrFail($messageId)->update(['read_at' => null]);
        $this->openId = null;
    }

    public function delete(int $messageId): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        $message = ContactMessage::findOrFail($messageId);
        Audit::log('message.deleted', "Deleted the contact message from {$message->name} (“{$message->subject}”)", $message);
        $message->delete();

        $this->openId = null;
        $this->toast('success', 'Message deleted.');
    }

    public function render()
    {
        abort_unless($this->user()->isAdmin(), 403);

        return view('livewire.studio.messages.index', [
            'messages' => ContactMessage::query()
                ->when($this->show === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->latest()->paginate(20),
            'current' => $this->openId ? ContactMessage::find($this->openId) : null,
            'unread' => ContactMessage::whereNull('read_at')->count(),
        ]);
    }
}
