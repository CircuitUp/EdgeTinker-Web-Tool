<div>
    <x-studio.page-header :title="'Welcome, '.$user->name"
        :intro="$user->isAdmin()
            ? 'You are the Content Admin. Changes you make go live immediately; changes from creators wait for your review.'
            : 'Changes you make are saved as drafts. Submit them for approval and the Content Admin will review them before they go live.'">
        <x-button :href="route('studio.products.create')"><x-icon name="cpu" class="h-4 w-4" /> Add a product</x-button>
    </x-studio.page-header>

    <div class="flex flex-col gap-6">
        @if ($user->isAdmin())
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['Waiting for your review', $queueTotal, route('studio.review.index')],
                    ['Creators awaiting activation', $pendingUsers, route('studio.users.index')],
                    ['Live products', $liveProducts, route('studio.products.index')],
                ] as [$label, $number, $link])
                    <a href="{{ $link }}" class="rounded-card border border-neutral-200 bg-white p-5 shadow-card transition-shadow hover:shadow-overlay">
                        <p class="font-mono text-4xl font-semibold text-neutral-900">{{ $number }}</p>
                        <p class="mt-1 text-base text-neutral-600">{{ $label }}</p>
                    </a>
                @endforeach
            </div>

            <x-studio.card title="Review queue" intro="Oldest first." flush>
                <x-slot:actions><x-button :href="route('studio.review.index')" variant="secondary" size="sm">Open the queue</x-button></x-slot:actions>
                @if ($queue->isEmpty())
                    <div class="p-5"><x-empty-state icon="check-circle" title="Nothing is waiting for review">New submissions from creators will appear here.</x-empty-state></div>
                @else
                    <ul class="divide-y divide-neutral-200">
                        @foreach ($queue as $revision)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('studio.review.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                                    <p class="text-sm text-neutral-600">{{ $typeLabels[$revision->entity_type] ?? $revision->entity_type }} · by {{ $revision->author?->name ?? 'a deleted user' }} · {{ $revision->submitted_at?->diffForHumans() }}</p>
                                </div>
                                <x-studio.revision-badges :revision="$revision" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-studio.card>
        @endif

        @if ($myRejected->isNotEmpty())
            <x-studio.card title="Changes requested" intro="The Content Admin sent these back with a comment." flush>
                <ul class="divide-y divide-neutral-200">
                    @foreach ($myRejected as $revision)
                        <li class="px-5 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <a href="{{ route('studio.revisions.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                                <x-studio.revision-badges :revision="$revision" />
                            </div>
                            <p class="mt-1 text-base text-neutral-700"><span class="font-medium">Comment:</span> {{ $revision->comment }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-studio.card>
        @endif

        @unless ($user->isAdmin() && $myDrafts->isEmpty() && $myWaiting->isEmpty())
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach (['Your drafts' => [$myDrafts, 'You have no drafts.'], 'Waiting for review' => [$myWaiting, 'Nothing is waiting for review.']] as $heading => [$items, $empty])
                    <x-studio.card :title="$heading" flush>
                        @if ($items->isEmpty())
                            <p class="p-5 text-base text-neutral-600">{{ $empty }}</p>
                        @else
                            <ul class="divide-y divide-neutral-200">
                                @foreach ($items as $revision)
                                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                        <div class="min-w-0">
                                            <a href="{{ route('studio.revisions.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                                            <p class="text-sm text-neutral-600">{{ $typeLabels[$revision->entity_type] ?? $revision->entity_type }} · updated {{ $revision->updated_at->diffForHumans() }}</p>
                                        </div>
                                        <x-studio.revision-badges :revision="$revision" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-studio.card>
                @endforeach
            </div>
        @endunless

        @if ($myApproved->isNotEmpty() && ! $user->isAdmin())
            <x-studio.card title="Recently approved" flush>
                <ul class="divide-y divide-neutral-200">
                    @foreach ($myApproved as $revision)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <a href="{{ route('studio.revisions.show', $revision) }}" class="text-base font-medium text-neutral-900 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                            <span class="text-sm text-neutral-600">{{ $revision->reviewed_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-studio.card>
        @endif

        @if ($user->isAdmin())
            <x-studio.card title="Recent activity" flush>
                <x-slot:actions><x-button :href="route('studio.audit.index')" variant="secondary" size="sm">Full audit log</x-button></x-slot:actions>
                <ul class="divide-y divide-neutral-200">
                    @forelse ($activity as $entry)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 px-5 py-2.5">
                            <p class="min-w-0 text-base text-neutral-900"><span class="font-medium">{{ $entry->user_name ?? 'System' }}</span> · {{ $entry->description }}</p>
                            <time class="text-sm text-neutral-600" datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->diffForHumans() }}</time>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-base text-neutral-600">No activity yet.</li>
                    @endforelse
                </ul>
            </x-studio.card>
        @endif
    </div>
</div>
