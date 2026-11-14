<div>
    <x-studio.page-header title="Messages" :intro="'Messages sent through the Contact Us form. '.$unread.' unread.'" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <x-studio.card flush>
            <div class="border-b border-neutral-200 p-4">
                <label for="message-show" class="text-sm font-medium text-neutral-900">Show</label>
                <select id="message-show" wire:model.live="show" class="mt-1 block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
                    <option value="all">All messages</option>
                    <option value="unread">Unread only</option>
                </select>
            </div>

            @if ($messages->isEmpty())
                <div class="p-5"><x-empty-state icon="mail" title="No messages">{{ $show === 'unread' ? 'Every message has been read.' : 'Messages from the contact form will appear here.' }}</x-empty-state></div>
            @else
                <ul class="divide-y divide-neutral-200">
                    @foreach ($messages as $message)
                        <li wire:key="message-{{ $message->id }}">
                            <button type="button" wire:click="open({{ $message->id }})" @if ($current?->id === $message->id) aria-current="true" @endif
                                    class="flex w-full flex-col gap-0.5 px-4 py-3 text-left hover:bg-primary-50 {{ $current?->id === $message->id ? 'bg-primary-100' : '' }}">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="min-w-0 truncate text-base {{ $message->read_at ? 'font-medium text-neutral-700' : 'font-bold text-neutral-900' }}">{{ $message->name }}</span>
                                    @unless ($message->read_at)<x-badge variant="info">Unread</x-badge>@endunless
                                </span>
                                <span class="truncate text-base {{ $message->read_at ? 'text-neutral-600' : 'font-semibold text-neutral-900' }}">{{ $message->subject }}</span>
                                <span class="text-sm text-neutral-600">{{ $message->created_at->format('j M Y, H:i') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-neutral-200 p-4">{{ $messages->links('pagination.studio') }}</div>
            @endif
        </x-studio.card>

        <x-studio.card>
            @if ($current)
                <h2 class="text-xl font-semibold text-neutral-900">{{ $current->subject }}</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-1 text-base sm:grid-cols-[auto_1fr]">
                    <dt class="text-neutral-600">From</dt>
                    <dd class="font-medium text-neutral-900">{{ $current->name }}</dd>
                    <dt class="text-neutral-600">Email</dt>
                    <dd class="[overflow-wrap:anywhere]"><a href="mailto:{{ $current->email }}?subject={{ rawurlencode('Re: '.$current->subject) }}" class="font-medium text-primary-700 underline underline-offset-2">{{ $current->email }}</a></dd>
                    @if ($current->company)
                        <dt class="text-neutral-600">Company</dt>
                        <dd class="text-neutral-900">{{ $current->company }}</dd>
                    @endif
                    <dt class="text-neutral-600">Received</dt>
                    <dd class="text-neutral-900">{{ $current->created_at->toDayDateTimeString() }}</dd>
                </dl>
                <p class="mt-5 whitespace-pre-line break-words border-t border-neutral-200 pt-5 text-base text-neutral-900">{{ $current->message }}</p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <x-button :href="'mailto:'.$current->email.'?subject='.rawurlencode('Re: '.$current->subject)"><x-icon name="mail" class="h-4 w-4" /> Reply by email</x-button>
                    <x-button variant="secondary" wire:click="markUnread({{ $current->id }})">Mark as unread</x-button>
                    <x-button variant="ghost-danger" wire:click="delete({{ $current->id }})" wire:confirm="Delete this message permanently?">Delete</x-button>
                </div>
            @else
                <x-empty-state icon="mail" title="Select a message">Choose a message from the list to read it.</x-empty-state>
            @endif
        </x-studio.card>
    </div>
</div>
