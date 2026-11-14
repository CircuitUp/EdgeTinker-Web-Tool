@props(['revision'])
{{-- Status and kind of a revision, e.g. "Edit · Awaiting review". --}}
<span class="inline-flex flex-wrap items-center gap-1.5">
    <x-badge :variant="$revision->action->variant()">{{ $revision->action->label() }}</x-badge>
    <x-badge :variant="$revision->status->variant()">{{ $revision->status->label() }}</x-badge>
    @if ($revision->needs_rebase)
        <x-badge variant="warning">Needs update</x-badge>
    @endif
</span>
