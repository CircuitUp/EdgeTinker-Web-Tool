<div>
    <x-studio.page-header title="Categories" intro="Categories appear in the Products menu, on the home page and in the filters. New categories appear there automatically once approved.">
        <x-button :href="route('studio.categories.create')">Add a category</x-button>
    </x-studio.page-header>

    <div class="flex flex-col gap-6">
        @if ($newCategories->isNotEmpty())
            <x-studio.card title="New categories, not live yet" flush>
                <ul class="divide-y divide-neutral-200">
                    @foreach ($newCategories as $revision)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <a href="{{ route('studio.revisions.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                                <p class="text-sm text-neutral-600">by {{ $revision->author?->name ?? 'a deleted user' }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-studio.revision-badges :revision="$revision" />
                                @if ($isAdmin || $revision->author_id === $userId)
                                    <x-button :href="route('studio.categories.revision', $revision)" variant="secondary" size="sm">Edit</x-button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-studio.card>
        @endif

        <x-studio.card flush>
            @if ($categories->isEmpty())
                <div class="p-5"><x-empty-state title="No categories yet">Add a category before adding products.</x-empty-state></div>
            @else
                <ul class="divide-y divide-neutral-200">
                    @foreach ($categories as $category)
                        @php
                            $open = $pending->get($category->id, collect());
                            $deletion = $open->first(fn ($r) => $r->action === \App\Enums\RevisionAction::Delete);
                            $mine = $open->first(fn ($r) => $r->author_id === $userId && $r->isEditable());
                        @endphp
                        <li wire:key="category-{{ $category->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3">
                            <div class="h-14 w-20 shrink-0 overflow-hidden rounded-control border border-neutral-200 bg-primary-50">
                                @if ($category->image_url)<img src="{{ $category->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">@endif
                            </div>
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="text-base font-semibold text-neutral-900">{{ $category->name }}</p>
                                <p class="text-sm text-neutral-600">{{ $category->tagline ?: 'No tagline' }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <x-badge>{{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}</x-badge>
                                    <x-badge>Order {{ $category->sort_order }}</x-badge>
                                    @if ($deletion)<x-badge variant="danger">Deletion pending</x-badge>@endif
                                    @foreach ($open->reject(fn ($r) => $r->action === \App\Enums\RevisionAction::Delete) as $revision)
                                        <a href="{{ route('studio.revisions.show', $revision) }}" class="rounded-full"><x-badge variant="warning" wrap>Edit by {{ $revision->author_id === $userId ? 'you' : ($revision->author?->name ?? 'unknown') }}: {{ strtolower($revision->status->label()) }}</x-badge></a>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-button :href="$mine ? route('studio.categories.revision', $mine) : route('studio.categories.edit', $category->id)" variant="secondary" size="sm">{{ $mine ? 'Continue editing' : 'Edit' }}</x-button>
                                <x-button :href="route('studio.history', ['type' => 'category', 'id' => $category->id])" variant="ghost" size="sm">History</x-button>
                                @unless ($deletion)
                                    <x-button variant="ghost-danger" size="sm" wire:click="requestDeletion({{ $category->id }})"
                                              wire:confirm="{{ $isAdmin ? 'Delete the category “'.$category->name.'” now?' : 'Ask the Content Admin to delete “'.$category->name.'”?' }}">
                                        {{ $isAdmin ? 'Delete' : 'Request deletion' }}
                                    </x-button>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-studio.card>
    </div>
</div>
