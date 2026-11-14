<div>
    <x-studio.page-header :title="'History: '.$name"
        :intro="$typeLabel.($isLive ? '. Every approved version, newest first.' : '. This item has been deleted; its earlier versions are kept here.')">
        @if ($liveUrl)
            <x-button :href="$liveUrl" variant="ghost" target="_blank" rel="noopener">View live page <x-icon name="external" class="h-4 w-4" /><span class="sr-only">(opens in a new tab)</span></x-button>
        @endif
    </x-studio.page-header>

    <div class="flex flex-col gap-6">
        @if ($pending->isNotEmpty())
            <x-studio.card title="Changes not yet approved" flush>
                <ul class="divide-y divide-neutral-200">
                    @foreach ($pending as $revision)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <p class="text-base text-neutral-900">
                                @if ($isAdmin || $revision->author_id === $userId)
                                    <a href="{{ route('studio.revisions.show', $revision) }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                                @else
                                    <span class="font-semibold">{{ $revision->title }}</span>
                                @endif
                                <span class="text-neutral-600">· by {{ $revision->author?->name ?? 'a deleted user' }} · {{ $revision->updated_at->diffForHumans() }}</span>
                            </p>
                            <x-studio.revision-badges :revision="$revision" />
                        </li>
                    @endforeach
                </ul>
            </x-studio.card>
        @endif

        <x-studio.card title="Approved versions" flush>
            @if ($versions->isEmpty())
                <p class="p-5 text-base text-neutral-600">This item was created before Content Studio kept history, so there are no earlier versions yet. The next approved change will appear here.</p>
            @else
                <ol class="divide-y divide-neutral-200">
                    @foreach ($versions as $version)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-base font-medium text-neutral-900">
                                    {{ $version->reviewed_at?->toDayDateTimeString() }}
                                    @if ($loop->first && $isLive)<x-badge variant="success" class="ml-1">Live now</x-badge>@endif
                                </p>
                                <p class="text-sm text-neutral-600">
                                    {{ $version->action->label() }} by {{ $version->author?->name ?? 'a deleted user' }}
                                    @if ($version->reviewer_id !== $version->author_id) · approved by {{ $version->reviewer?->name ?? 'a deleted user' }} @endif
                                </p>
                            </div>
                            @if ($isAdmin || $version->author_id === $userId)
                                <x-button :href="route('studio.revisions.show', $version)" variant="secondary" size="sm">
                                    {{ $isAdmin && ! ($loop->first && $isLive) ? 'View or restore' : 'View' }}<span class="sr-only"> version of {{ $version->reviewed_at?->toDayDateTimeString() }}</span>
                                </x-button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-studio.card>
    </div>
</div>
