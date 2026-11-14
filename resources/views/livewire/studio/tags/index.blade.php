@php $control = 'block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500 hover:border-neutral-400'; @endphp
<div>
    <x-studio.page-header title="Tags" intro="A tag must exist here before it can be used on a product. Tags become filters on listing pages and clickable chips on product pages." />

    <div class="flex flex-col gap-6">
        <x-studio.card title="Add a tag">
            <form wire:submit="add" class="flex max-w-lg items-end gap-2">
                <div class="flex-1">
                    <label for="new-tag-name" class="text-sm font-medium text-neutral-900">Tag name</label>
                    <input id="new-tag-name" type="text" wire:model="newName" maxlength="60" class="{{ $control }} mt-1" placeholder="e.g. LoRaWAN" @error('newName') aria-invalid="true" @enderror>
                </div>
                <x-button type="submit">{{ $isAdmin ? 'Create tag' : 'Submit tag' }}</x-button>
            </form>
            @error('newName')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        </x-studio.card>

        @if ($newTags->isNotEmpty())
            <x-studio.card title="New tags waiting for review" flush>
                <ul class="divide-y divide-neutral-200">
                    @foreach ($newTags as $revision)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <a href="{{ route('studio.revisions.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                            <span class="flex items-center gap-2 text-sm text-neutral-600">by {{ $revision->author?->name ?? 'a deleted user' }} <x-studio.revision-badges :revision="$revision" /></span>
                        </li>
                    @endforeach
                </ul>
            </x-studio.card>
        @endif

        <x-studio.card :title="$tags->count().' '.Str::plural('tag', $tags->count())" flush>
            @if ($tags->isEmpty())
                <div class="p-5"><x-empty-state title="No tags yet">Create your first tag above.</x-empty-state></div>
            @else
                <ul class="divide-y divide-neutral-200">
                    @foreach ($tags as $tag)
                        @php $open = $pending->get($tag->id, collect()); @endphp
                        <li wire:key="tag-{{ $tag->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-2.5">
                            @if ($editingId === $tag->id)
                                <form wire:submit="rename" class="flex flex-1 flex-wrap items-center gap-2">
                                    <label for="rename-{{ $tag->id }}" class="sr-only">New name for {{ $tag->name }}</label>
                                    <input id="rename-{{ $tag->id }}" type="text" wire:model="editingName" maxlength="60" class="{{ $control }} max-w-xs" autofocus>
                                    <x-button type="submit" size="sm">Save</x-button>
                                    <x-button variant="ghost" size="sm" wire:click="$set('editingId', null)">Cancel</x-button>
                                    @error('editingName')<p class="w-full text-sm font-medium text-danger">{{ $message }}</p>@enderror
                                </form>
                            @else
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-tag-chip>{{ $tag->name }}</x-tag-chip>
                                    <span class="text-sm text-neutral-600">{{ $tag->products_count }} {{ Str::plural('product', $tag->products_count) }}</span>
                                    @foreach ($open as $revision)
                                        <a href="{{ route('studio.revisions.show', $revision) }}" class="rounded-full">
                                            <x-badge :variant="$revision->action === \App\Enums\RevisionAction::Delete ? 'danger' : 'warning'">{{ $revision->action === \App\Enums\RevisionAction::Delete ? 'Deletion pending' : 'Rename pending: '.$revision->title }}</x-badge>
                                        </a>
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-button variant="secondary" size="sm" wire:click="startEditing({{ $tag->id }})">Rename<span class="sr-only"> {{ $tag->name }}</span></x-button>
                                    <x-button variant="ghost-danger" size="sm" wire:click="requestDeletion({{ $tag->id }})"
                                              wire:confirm="{{ $isAdmin ? 'Delete the tag “'.$tag->name.'”? It will be removed from '.$tag->products_count.' product(s).' : 'Ask the Content Admin to delete the tag “'.$tag->name.'”?' }}">
                                        {{ $isAdmin ? 'Delete' : 'Request deletion' }}<span class="sr-only"> {{ $tag->name }}</span>
                                    </x-button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-studio.card>
    </div>
</div>
