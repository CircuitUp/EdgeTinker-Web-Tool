@php $select = 'mt-1 block min-h-11 rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400'; @endphp
<div>
    <x-studio.page-header :title="$reviewing ? 'Review queue' : 'My submissions'"
        :intro="$reviewing
            ? 'Changes from Content Creators. Nothing here is on the live site until you approve it.'
            : 'Your drafts, submissions and their outcomes.'" />

    <x-studio.card flush>
        <div class="flex flex-wrap items-end gap-3 border-b border-neutral-200 p-4">
            <div>
                <label for="revision-status" class="text-sm font-medium text-neutral-900">Status</label>
                <select id="revision-status" wire:model.live="status" class="{{ $select }}">
                    <option value="all">All</option>
                    <option value="open">Not yet approved</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="revision-type" class="text-sm font-medium text-neutral-900">Content</label>
                <select id="revision-type" wire:model.live="type" class="{{ $select }}">
                    <option value="">Everything</option>
                    @foreach ($typeLabels as $key => $label)
                        <option value="{{ $key }}">{{ Str::plural($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($revisions->isEmpty())
            <div class="p-5">
                <x-empty-state icon="check-circle" :title="$reviewing ? 'Nothing to review' : 'Nothing here yet'">
                    {{ $reviewing ? 'No changes match this filter.' : 'Changes you save or submit will appear here.' }}
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-neutral-200" wire:loading.class="opacity-60">
                @foreach ($revisions as $revision)
                    <li wire:key="revision-{{ $revision->id }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route($reviewing ? 'studio.review.show' : 'studio.revisions.show', $revision) }}" class="text-base font-semibold text-primary-700 underline-offset-2 hover:underline">{{ $revision->title }}</a>
                            <p class="text-sm text-neutral-600">
                                {{ $typeLabels[$revision->entity_type] ?? $revision->entity_type }}
                                @if ($reviewing) · by {{ $revision->author?->name ?? 'a deleted user' }} @endif
                                · {{ $revision->submitted_at ? 'submitted '.$revision->submitted_at->diffForHumans() : 'saved '.$revision->updated_at->diffForHumans() }}
                                @if ($revision->reviewed_at) · reviewed {{ $revision->reviewed_at->diffForHumans() }} by {{ $revision->reviewer?->name ?? 'a deleted user' }} @endif
                            </p>
                        </div>
                        <x-studio.revision-badges :revision="$revision" />
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-neutral-200 p-4">{{ $revisions->links('pagination.studio') }}</div>
        @endif
    </x-studio.card>
</div>
