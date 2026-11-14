<div>
    <x-studio.page-header title="Wiki" intro="Documentation is organised by category, then product, then pages. Open a product to see and edit its pages." />

    <div class="flex flex-col gap-6">
        @forelse ($categories as $category)
            <x-studio.card :title="$category->name" flush>
                @if ($category->products->isEmpty())
                    <p class="p-5 text-base text-neutral-600">This category has no products yet.</p>
                @else
                    <ul class="divide-y divide-neutral-200">
                        @foreach ($category->products as $product)
                            @php
                                $isOpen = $open === $product->id;
                                $new = $newByProduct->get($product->id, collect());
                            @endphp
                            <li wire:key="wiki-product-{{ $product->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                    <button type="button" wire:click="toggle({{ $product->id }})" aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                            class="flex min-h-11 min-w-0 flex-1 basis-56 items-center gap-2 rounded-control text-left text-base font-semibold text-neutral-900 hover:text-primary-700">
                                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform {{ $isOpen ? 'rotate-90' : '' }}" />
                                        <span class="min-w-0 truncate">{{ $product->name }}</span>
                                    </button>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-badge>{{ $product->wiki_pages_count }} live {{ Str::plural('page', $product->wiki_pages_count) }}</x-badge>
                                        @if ($new->isNotEmpty())<x-badge variant="warning">{{ $new->count() }} not live yet</x-badge>@endif
                                        <x-button :href="route('studio.wiki.create', ['product' => $product->id])" variant="secondary" size="sm">Add a page<span class="sr-only"> to {{ $product->name }}</span></x-button>
                                    </div>
                                </div>

                                @if ($isOpen)
                                    <div class="border-t border-neutral-200 bg-neutral-50 px-5 py-4">
                                        @if ($pages->isEmpty() && $new->isEmpty())
                                            <p class="text-base text-neutral-600">No wiki pages yet.</p>
                                        @endif

                                        @if ($pages->isNotEmpty())
                                            <ul class="flex flex-col divide-y divide-neutral-200 rounded-card border border-neutral-200 bg-white">
                                                @foreach ($pages as $page)
                                                    @php
                                                        $pending = $pendingByPage->get($page->id, collect());
                                                        $deletion = $pending->first(fn ($r) => $r->action === \App\Enums\RevisionAction::Delete);
                                                        $mine = $pending->first(fn ($r) => $r->author_id === $userId && $r->isEditable());
                                                    @endphp
                                                    <li wire:key="wiki-page-{{ $page->id }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2.5">
                                                        <div class="min-w-0 flex-1 basis-48">
                                                            <p class="text-base font-medium text-neutral-900">{{ $page->sort_order }}. {{ $page->title }}</p>
                                                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                                                <span class="text-sm text-neutral-600">Updated {{ $page->updated_at->diffForHumans() }}</span>
                                                                @if ($deletion)<x-badge variant="danger">Deletion pending</x-badge>@endif
                                                                @foreach ($pending->reject(fn ($r) => $r->action === \App\Enums\RevisionAction::Delete) as $revision)
                                                                    <a href="{{ route('studio.revisions.show', $revision) }}" class="rounded-card"><x-badge variant="warning" wrap>Edit by {{ $revision->author_id === $userId ? 'you' : ($revision->author?->name ?? 'unknown') }}: {{ strtolower($revision->status->label()) }}</x-badge></a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-wrap items-center gap-1">
                                                            <x-button :href="$mine ? route('studio.wiki.revision', $mine) : route('studio.wiki.edit', $page->id)" variant="secondary" size="sm">{{ $mine ? 'Continue editing' : 'Edit' }}<span class="sr-only"> {{ $page->title }}</span></x-button>
                                                            <x-button :href="route('studio.history', ['type' => 'wiki_page', 'id' => $page->id])" variant="ghost" size="sm">History<span class="sr-only"> of {{ $page->title }}</span></x-button>
                                                            <x-button :href="route('wiki.show', [$category->slug, $product->slug, $page->slug])" variant="ghost" size="sm" target="_blank" rel="noopener">View<span class="sr-only"> {{ $page->title }} (opens in a new tab)</span></x-button>
                                                            @unless ($deletion)
                                                                <x-button variant="ghost-danger" size="sm" wire:click="requestDeletion({{ $page->id }})"
                                                                          wire:confirm="{{ $isAdmin ? 'Delete the page “'.$page->title.'” now?' : 'Ask the Content Admin to delete “'.$page->title.'”?' }}">{{ $isAdmin ? 'Delete' : 'Request deletion' }}<span class="sr-only"> {{ $page->title }}</span></x-button>
                                                            @endunless
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if ($new->isNotEmpty())
                                            <p class="mb-2 mt-4 text-sm font-semibold uppercase tracking-wider text-neutral-600">New pages, not live yet</p>
                                            <ul class="flex flex-col divide-y divide-neutral-200 rounded-card border border-dashed border-neutral-300 bg-white">
                                                @foreach ($new->sortBy(fn ($r) => $r->payload['sort_order'] ?? 0) as $revision)
                                                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5">
                                                        <p class="min-w-0 text-base text-neutral-900">
                                                            <span class="font-medium">{{ $revision->payload['title'] ?? $revision->title }}</span>
                                                            <span class="text-sm text-neutral-600">· by {{ $revision->author_id === $userId ? 'you' : ($revision->author?->name ?? 'a deleted user') }}</span>
                                                        </p>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <x-studio.revision-badges :revision="$revision" />
                                                            @if ($isAdmin || $revision->author_id === $userId)
                                                                <x-button :href="route('studio.wiki.revision', $revision)" variant="secondary" size="sm">Edit<span class="sr-only"> {{ $revision->payload['title'] ?? '' }}</span></x-button>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        <x-button variant="ghost" size="sm" class="mt-3" wire:click="addStandardPages({{ $product->id }})"
                                                  wire:confirm="Create drafts for the standard pages this product does not have yet (Overview, Quick Start, Datasheet, Pinout, Schematics, Firmware & Software, Downloads)?">
                                            Add the standard set of pages as drafts
                                        </x-button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-studio.card>
        @empty
            <x-empty-state title="No categories yet">Add a category and a product first; wiki pages belong to products.</x-empty-state>
        @endforelse
    </div>
</div>
