<div>
    <x-studio.page-header title="Media library" intro="Every image and document uploaded to the site. Images are resized automatically for fast loading." />

    <div class="flex flex-col gap-6">
        <x-studio.card title="Upload files">
            <div x-data="{ over: false }" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop="over = false"
                 :class="over ? 'border-primary-600 bg-primary-50' : 'border-neutral-300 bg-neutral-50'"
                 class="relative flex flex-col items-center justify-center rounded-card border-2 border-dashed px-4 py-8 text-center has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary-600">
                <x-icon name="box" class="h-8 w-8 text-primary-600" />
                <p class="mt-2 text-base font-medium text-neutral-900">Drop files here, or click to choose</p>
                <p class="text-sm text-neutral-600">Images: JPG, PNG, WebP, GIF up to 8 MB. Documents: PDF, ZIP, STEP, STL, BIN, HEX, UF2, TXT, CSV up to 30 MB.</p>
                <input type="file" multiple wire:model="uploads" aria-label="Upload files"
                       accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.zip,.step,.stp,.stl,.bin,.hex,.uf2,.txt,.csv" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                <p wire:loading wire:target="uploads" class="mt-2 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
            </div>
            @foreach ($errors->get('uploads') as $message)
                <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-danger"><x-icon name="alert" class="h-4 w-4" />{{ $message }}</p>
            @endforeach
        </x-studio.card>

        <x-studio.card flush>
            <div class="flex flex-wrap items-end gap-3 border-b border-neutral-200 p-4">
                <div class="min-w-48 flex-1">
                    <label for="media-search" class="text-sm font-medium text-neutral-900">Search by file name</label>
                    <input id="media-search" type="search" wire:model.live.debounce.300ms="search"
                           class="mt-1 block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
                </div>
                <div>
                    <label for="media-kind" class="text-sm font-medium text-neutral-900">Show</label>
                    <select id="media-kind" wire:model.live="kind" class="mt-1 block min-h-11 rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
                        <option value="all">Images and documents</option>
                        <option value="image">Images only</option>
                        <option value="document">Documents only</option>
                    </select>
                </div>
            </div>

            @if ($files->isEmpty())
                <div class="p-5"><x-empty-state title="No files found">{{ $search !== '' || $kind !== 'all' ? 'Try a different search or filter.' : 'Upload your first file above.' }}</x-empty-state></div>
            @else
                <ul class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-4" wire:loading.class="opacity-60">
                    @foreach ($files as $file)
                        <li wire:key="media-{{ $file->id }}" class="flex flex-col overflow-hidden rounded-card border border-neutral-200 bg-white">
                            <div class="flex aspect-[4/3] items-center justify-center bg-neutral-50">
                                @if ($file->isImage())
                                    <img src="{{ $file->variantUrl(400) }}" alt="" loading="lazy" class="h-full w-full object-contain">
                                @else
                                    <span class="flex flex-col items-center gap-1 text-neutral-600">
                                        <x-icon name="book" class="h-8 w-8" />
                                        <span class="font-mono text-sm uppercase">{{ pathinfo($file->original_name, PATHINFO_EXTENSION) }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col gap-1 p-3">
                                <p class="truncate text-sm font-medium text-neutral-900" title="{{ $file->original_name }}">{{ $file->original_name }}</p>
                                <p class="text-sm text-neutral-600">
                                    {{ $file->humanSize() }}@if ($file->width) · {{ $file->width }}×{{ $file->height }}@endif
                                </p>
                                <p class="text-sm text-neutral-600">{{ $file->uploader?->name ?? 'Unknown' }} · {{ $file->created_at->diffForHumans() }}</p>
                                @if ($file->isImage() && empty($file->variants))
                                    <x-badge variant="warning" class="self-start">Resizing queued</x-badge>
                                @endif
                                <div class="mt-auto flex items-center gap-1 pt-2">
                                    <x-button :href="$file->url" variant="ghost" size="sm" target="_blank" rel="noopener">Open<span class="sr-only"> {{ $file->original_name }} (opens in a new tab)</span></x-button>
                                    @if ($isAdmin || $file->uploaded_by === $userId)
                                        <x-button variant="ghost-danger" size="sm" wire:click="delete({{ $file->id }})"
                                                  wire:confirm="Delete “{{ $file->original_name }}”? Files that are in use cannot be deleted.">Delete<span class="sr-only"> {{ $file->original_name }}</span></x-button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-neutral-200 p-4">{{ $files->links('pagination.studio') }}</div>
            @endif
        </x-studio.card>
    </div>
</div>
