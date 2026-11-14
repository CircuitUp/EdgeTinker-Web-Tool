<div>
    <x-studio.page-header title="Pages" intro="Edit the Home, Services and Contact pages through forms. Changes go through the same approval as everything else." />

    <x-studio.card flush>
        <ul class="divide-y divide-neutral-200">
            @foreach ($pages as $page)
                @php
                    $open = $pending->get($page['id'], collect());
                    $mine = $open->first(fn ($r) => $r->author_id === $userId && $r->isEditable());
                @endphp
                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-5 py-4">
                    <div class="min-w-0 flex-1 basis-64">
                        <p class="text-lg font-semibold text-neutral-900">{{ $page['title'] }}</p>
                        <p class="text-base text-neutral-600">{{ $page['about'] }}</p>
                        @if ($open->isNotEmpty())
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach ($open as $revision)
                                    <a href="{{ route('studio.revisions.show', $revision) }}" class="rounded-card">
                                        <x-badge variant="warning" wrap>Edit by {{ $revision->author_id === $userId ? 'you' : ($revision->author?->name ?? 'unknown') }}: {{ strtolower($revision->status->label()) }}</x-badge>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button :href="$mine ? route('studio.pages.revision', $mine) : route('studio.pages.edit', $page['slug'])" variant="secondary" size="sm">
                            {{ $mine ? 'Continue editing' : 'Edit' }}<span class="sr-only"> {{ $page['title'] }}</span>
                        </x-button>
                        <x-button :href="route('studio.history', ['type' => 'page', 'id' => $page['id']])" variant="ghost" size="sm">History<span class="sr-only"> of {{ $page['title'] }}</span></x-button>
                        <x-button :href="$page['url']" variant="ghost" size="sm" target="_blank" rel="noopener">View<span class="sr-only"> {{ $page['title'] }} (opens in a new tab)</span></x-button>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-studio.card>
</div>
