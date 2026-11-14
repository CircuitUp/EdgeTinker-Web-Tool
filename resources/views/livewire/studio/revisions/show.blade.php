@use('App\Enums\RevisionAction')
@use('App\Enums\RevisionStatus')
@php
    // A summary value is either a line of text or a list of lines.
    $cell = function ($value) {
        if (is_array($value)) {
            return $value === [] ? ['(none)'] : $value;
        }

        return [($value === null || $value === '') ? '(empty)' : $value];
    };
    $visibleRows = $onlyChanges && $hasBefore && $hasAfter ? collect($rows)->where('changed', true) : collect($rows);
@endphp
<div>
    <x-studio.page-header :title="$revision->title" :back="$backUrl" :back-label="$fromReview ? 'Review queue' : 'My submissions'">
        @if ($liveUrl)
            <x-button :href="$liveUrl" variant="ghost" target="_blank" rel="noopener">View live page <x-icon name="external" class="h-4 w-4" /><span class="sr-only">(opens in a new tab)</span></x-button>
        @endif
        @if ($historyUrl)
            <x-button :href="$historyUrl" variant="ghost">History</x-button>
        @endif
        @if ($editUrl)
            <x-button :href="$editUrl" variant="secondary">Edit</x-button>
        @endif
    </x-studio.page-header>

    <div class="flex flex-col gap-6">
        {{-- Who, what, when --}}
        <x-studio.card>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <x-studio.revision-badges :revision="$revision" />
                <p class="text-base text-neutral-700">
                    <span class="font-medium text-neutral-900">{{ $typeLabel }}</span>
                    · {{ match ($revision->action) { RevisionAction::Create => 'new item', RevisionAction::Update => 'changes to a live item', RevisionAction::Delete => 'request to delete a live item' } }}
                    · by <span class="font-medium text-neutral-900">{{ $revision->author?->name ?? 'a deleted user' }}</span>
                    @if ($revision->submitted_at) · submitted {{ $revision->submitted_at->toDayDateTimeString() }} @endif
                </p>
            </div>
            @if ($revision->reviewed_at)
                <p class="mt-3 text-base text-neutral-700">
                    {{ $revision->status->label() }} by <span class="font-medium text-neutral-900">{{ $revision->reviewer?->name ?? 'a deleted user' }}</span>
                    on {{ $revision->reviewed_at->toDayDateTimeString() }}.
                </p>
            @endif
        </x-studio.card>

        @if ($revision->needs_rebase)
            <x-studio.alert variant="warning" title="This change is out of date">
                {{ $revision->rebase_note }}
                @if ($isAdmin) It cannot be approved until its author has updated and resubmitted it. @endif
            </x-studio.alert>
        @endif
        @if ($revision->comment)
            <x-studio.alert :variant="$revision->status === RevisionStatus::Rejected ? 'danger' : 'info'"
                            :title="$revision->status === RevisionStatus::Rejected ? 'Changes requested by the reviewer' : 'Comment from the reviewer'">
                <p class="whitespace-pre-line">{{ $revision->comment }}</p>
            </x-studio.alert>
        @endif
        @if ($revision->action === RevisionAction::Delete && $revision->status !== RevisionStatus::Approved)
            <x-studio.alert variant="danger" title="This is a request to delete the item below">
                It stays on the live site until the Content Admin approves the deletion.
            </x-studio.alert>
        @endif

        {{-- Before / after --}}
        <x-studio.card flush
            :title="match (true) { ! $hasBefore => 'Proposed content', ! $hasAfter => 'Content that will be removed', default => 'Before and after' }"
            :intro="$hasBefore && $hasAfter ? $changedCount.' '.Str::plural('field', $changedCount).' changed.' : null">
            @if ($hasBefore && $hasAfter)
                <x-slot:actions>
                    <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-sm font-medium text-neutral-900">
                        <input type="checkbox" wire:model.live="onlyChanges" class="h-4 w-4 accent-primary-600"> Show only what changed
                    </label>
                </x-slot:actions>
            @endif

            @if ($visibleRows->isEmpty())
                <p class="p-5 text-base text-neutral-600">Nothing differs from the live version.</p>
            @else
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Comparison of live and proposed content">
                    <table class="w-full border-collapse text-left text-base">
                        <caption class="sr-only">Comparison of the live content and the proposed content</caption>
                        <thead class="bg-neutral-50 text-sm font-semibold text-neutral-600">
                            <tr>
                                <th scope="col" class="w-44 px-5 py-3">Field</th>
                                @if ($hasBefore)<th scope="col" class="px-5 py-3">{{ $hasAfter ? ($revision->status === RevisionStatus::Approved ? 'Before' : 'Live now') : 'Live now' }}</th>@endif
                                @if ($hasAfter)<th scope="col" class="px-5 py-3">{{ $revision->status === RevisionStatus::Approved ? 'After' : 'Proposed' }}</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($visibleRows as $row)
                                <tr class="border-t border-neutral-200 align-top">
                                    <th scope="row" class="px-5 py-3 font-medium text-neutral-900">
                                        {{ $row['label'] }}
                                        @if ($row['changed'])<span class="sr-only">(changed)</span>@endif
                                    </th>
                                    @if ($hasBefore)
                                        <td class="px-5 py-3 text-neutral-700 {{ $row['changed'] ? 'bg-danger-50' : '' }}">
                                            @foreach ($cell($row['before']) as $line)<p class="whitespace-pre-line break-words">{{ $line }}</p>@endforeach
                                        </td>
                                    @endif
                                    @if ($hasAfter)
                                        <td class="px-5 py-3 text-neutral-900 {{ $row['changed'] ? 'bg-success-50 font-medium' : '' }}">
                                            @foreach ($cell($row['after']) as $line)<p class="whitespace-pre-line break-words">{{ $line }}</p>@endforeach
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-studio.card>

        {{-- Preview --}}
        @if ($previewUrl)
            <x-studio.card flush title="Preview" :intro="$revision->action === RevisionAction::Delete ? 'The page that would be removed.' : 'The public page as it would look with this change live.'">
                <x-slot:actions>
                    <x-button :href="$previewUrl" variant="secondary" size="sm" target="_blank" rel="noopener">Open in a new tab <x-icon name="external" class="h-4 w-4" /></x-button>
                </x-slot:actions>
                <iframe src="{{ $previewUrl }}" title="Preview of {{ $revision->title }}" loading="lazy" class="h-[42rem] w-full rounded-b-card bg-white"></iframe>
            </x-studio.card>
        @endif

        {{-- Review (Content Admin) --}}
        @if ($canReview)
            <x-studio.card title="Your decision">
                <x-form.field label="Comment for the creator" for="review-comment" :error="$errors->first('comment')"
                              hint="Required when rejecting: say what needs to change. Optional when approving.">
                    <textarea id="review-comment" rows="3" wire:model="comment" maxlength="2000"
                              class="block w-full rounded-control border border-neutral-300 bg-white px-3 py-2 text-base hover:border-neutral-400"></textarea>
                </x-form.field>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <x-button variant="danger" wire:click="reject" wire:loading.attr="disabled">Reject with comment</x-button>
                    <x-button wire:click="approve" wire:loading.attr="disabled" :disabled="$revision->needs_rebase"
                              wire:confirm="{{ $revision->action === RevisionAction::Delete ? 'Approve this deletion? “'.$revision->title.'” will be removed from the live site.' : 'Approve and publish “'.$revision->title.'” to the live site?' }}">
                        {{ $revision->action === RevisionAction::Delete ? 'Approve deletion' : 'Approve and publish' }}
                    </x-button>
                </div>
            </x-studio.card>
        @endif

        {{-- Author / housekeeping actions --}}
        @if ($canSubmit || $canDiscard || $canRestore)
            <div class="flex flex-wrap justify-end gap-2">
                @if ($canDiscard)
                    <x-button variant="ghost-danger" wire:click="discard"
                              wire:confirm="Discard this pending change? The live site is not affected. This cannot be undone.">
                        {{ $revision->status === RevisionStatus::Submitted ? 'Withdraw and discard' : 'Discard' }}
                    </x-button>
                @endif
                @if ($canRestore)
                    <x-button variant="secondary" wire:click="restore" wire:confirm="Publish this earlier version again? It replaces what is live now.">Restore this version</x-button>
                @endif
                @if ($canSubmit)
                    <x-button wire:click="submit" wire:loading.attr="disabled">{{ $isAdmin ? 'Publish now' : 'Submit for approval' }}</x-button>
                @endif
            </div>
        @endif
    </div>
</div>
