<div>
    <x-studio.page-header title="Products" intro="Live products, and new products waiting to go live.">
        <x-button :href="route('studio.products.create')"><x-icon name="cpu" class="h-4 w-4" /> Add a product</x-button>
    </x-studio.page-header>

    <div class="flex flex-col gap-6">
        @if ($newProducts->isNotEmpty())
            <x-studio.card title="New products, not live yet" intro="These exist only as drafts or submissions until the Content Admin approves them." flush>
                <ul class="divide-y divide-neutral-200">
                    @foreach ($newProducts as $revision)
                        @php $canEdit = $isAdmin || $revision->author_id === $userId; @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('studio.revisions.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                                <p class="text-sm text-neutral-600">by {{ $revision->author?->name ?? 'a deleted user' }} · updated {{ $revision->updated_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-studio.revision-badges :revision="$revision" />
                                @if ($canEdit)
                                    <x-button :href="route('studio.products.revision', $revision)" variant="secondary" size="sm">Edit</x-button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-studio.card>
        @endif

        <x-studio.card flush>
            <div class="flex flex-wrap items-end gap-3 border-b border-neutral-200 p-4">
                <div class="min-w-48 flex-1">
                    <label for="product-search" class="text-sm font-medium text-neutral-900">Search by name or SKU</label>
                    <input id="product-search" type="search" wire:model.live.debounce.300ms="search" placeholder="e.g. ESP32 or ET-DEV"
                           class="mt-1 block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 text-base placeholder:text-neutral-500 hover:border-neutral-400">
                </div>
                <div>
                    <label for="product-show" class="text-sm font-medium text-neutral-900">Show</label>
                    <select id="product-show" wire:model.live="show" class="mt-1 block min-h-11 rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
                        <option value="all">All live products</option>
                        <option value="pending">With changes pending</option>
                        <option value="featured">Featured on the home page</option>
                    </select>
                </div>
            </div>

            @if ($products->isEmpty())
                <div class="p-5">
                    <x-empty-state icon="search" title="No products found">
                        {{ $search !== '' || $show !== 'all' ? 'Try a different search or filter.' : 'Add your first product to get started.' }}
                    </x-empty-state>
                </div>
            @else
                <ul class="divide-y divide-neutral-200" wire:loading.class="opacity-60">
                    @foreach ($products as $product)
                        @php
                            $open = $pending->get($product->id, collect());
                            $deletion = $open->first(fn ($r) => $r->action === \App\Enums\RevisionAction::Delete);
                            $mine = $open->first(fn ($r) => $r->author_id === $userId && $r->isEditable());
                        @endphp
                        <li wire:key="product-{{ $product->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3">
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-control border border-neutral-200 bg-neutral-50">
                                @if ($product->thumbnail)
                                    <img src="{{ $product->thumbnail->sized(400) }}" alt="" loading="lazy" class="h-full w-full object-contain">
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="text-base font-semibold text-neutral-900">{{ $product->name }}</p>
                                <p class="text-sm text-neutral-600"><span class="font-mono">{{ $product->sku }}</span> · {{ $product->category?->name }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <x-stock-badge :status="$product->stock_status" />
                                    @if ($product->is_featured)<x-badge variant="info">Featured</x-badge>@endif
                                    @if ($deletion)
                                        <x-badge variant="danger">Deletion pending</x-badge>
                                    @endif
                                    @foreach ($open->reject(fn ($r) => $r->action === \App\Enums\RevisionAction::Delete) as $revision)
                                        <a href="{{ route('studio.revisions.show', $revision) }}" class="rounded-full underline-offset-2 hover:underline">
                                            <x-badge variant="warning" wrap>Edit by {{ $revision->author_id === $userId ? 'you' : ($revision->author?->name ?? 'unknown') }}: {{ strtolower($revision->status->label()) }}</x-badge>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-button :href="$mine ? route('studio.products.revision', $mine) : route('studio.products.edit', $product->id)" variant="secondary" size="sm">
                                    {{ $mine ? 'Continue editing' : 'Edit' }}
                                </x-button>
                                <x-button :href="route('studio.history', ['type' => 'product', 'id' => $product->id])" variant="ghost" size="sm">History</x-button>
                                <x-button :href="route('products.show', $product->slug)" variant="ghost" size="sm" target="_blank" rel="noopener">View<span class="sr-only"> {{ $product->name }} on the live site (opens in a new tab)</span></x-button>
                                @unless ($deletion)
                                    <x-button variant="ghost-danger" size="sm"
                                              wire:click="requestDeletion({{ $product->id }})"
                                              wire:confirm="{{ $isAdmin ? 'Delete “'.$product->name.'” from the live site now? This cannot be undone from here (you can restore it from History).' : 'Ask the Content Admin to delete “'.$product->name.'”? It stays live until they approve.' }}">
                                        {{ $isAdmin ? 'Delete' : 'Request deletion' }}
                                    </x-button>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-neutral-200 p-4">{{ $products->links('pagination.studio') }}</div>
            @endif
        </x-studio.card>
    </div>
</div>
