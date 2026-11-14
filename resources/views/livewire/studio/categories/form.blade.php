<div>
    <x-studio.page-header :title="$entityId ? 'Edit category' : 'Add a category'" :back="route('studio.categories.index')" back-label="All categories" />

    <div class="flex max-w-3xl flex-col gap-6">
        @if ($revision?->needs_rebase)
            <x-studio.alert variant="warning" title="This category changed while you were editing">{{ $revision->rebase_note }}</x-studio.alert>
        @endif
        @if ($revision?->status === \App\Enums\RevisionStatus::Rejected)
            <x-studio.alert variant="danger" title="The Content Admin asked for changes">{{ $revision->comment }}</x-studio.alert>
        @endif

        <x-studio.card>
            <div class="grid gap-5">
                <x-studio.input label="Name" model="form.name" required placeholder="e.g. Sensor Boards" />
                <x-studio.input label="Tagline" model="form.tagline" hint="One short line shown in the Products menu and on the category page." />
                <x-studio.input label="Display order" model="form.sort_order" type="number" min="0" class="max-w-40" hint="Lower numbers come first." />
                <x-studio.input label="Web address" model="form.slug" mono hint="Filled in from the name when left empty. Shown as /products/your-address." />

                <div>
                    <p class="text-sm font-medium text-neutral-900">Menu image</p>
                    <p class="text-sm text-neutral-600">Shown in the Products mega menu and on the home page tile. A 4:3 image works best.</p>
                    @if ($imageUrl)
                        <div class="mt-2 flex items-start gap-3">
                            <img src="{{ $imageUrl }}" alt="Current menu image" class="h-28 w-40 rounded-control border border-neutral-200 object-cover">
                            <x-button variant="ghost-danger" size="sm" wire:click="removeImage">Remove image</x-button>
                        </div>
                    @endif
                    <label for="category-image" class="sr-only">Upload a menu image</label>
                    <input id="category-image" type="file" wire:model="imageUpload" accept=".jpg,.jpeg,.png,.webp,.gif"
                           class="mt-2 block w-full text-base text-neutral-700 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-primary-600 file:bg-white file:px-4 file:font-semibold file:text-primary-700 hover:file:bg-primary-50">
                    <p wire:loading wire:target="imageUpload" class="mt-1 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
                    @error('imageUpload')<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    @error('form.image.media_id')<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-studio.card>

        <div class="flex flex-wrap justify-end gap-2">
            <x-button variant="secondary" wire:click="saveDraft" wire:loading.attr="disabled">Save draft</x-button>
            <x-button wire:click="submit" wire:loading.attr="disabled">{{ $isAdmin ? 'Publish now' : 'Submit for approval' }}</x-button>
        </div>
    </div>
</div>
