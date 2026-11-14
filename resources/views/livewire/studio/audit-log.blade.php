<div>
    <x-studio.page-header title="Audit log" intro="A permanent record of who did what and when in Content Studio." />

    <x-studio.card flush>
        <div class="flex flex-wrap items-end gap-3 border-b border-neutral-200 p-4">
            <div class="min-w-48 flex-1">
                <label for="audit-search" class="text-sm font-medium text-neutral-900">Search by person or description</label>
                <input id="audit-search" type="search" wire:model.live.debounce.300ms="search"
                       class="mt-1 block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
            </div>
            <div>
                <label for="audit-area" class="text-sm font-medium text-neutral-900">Area</label>
                <select id="audit-area" wire:model.live="area" class="mt-1 block min-h-11 rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
                    <option value="">Everything</option>
                    @foreach ($areas as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($entries->isEmpty())
            <div class="p-5"><x-empty-state title="No entries found">Try a different search or area.</x-empty-state></div>
        @else
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Audit log entries" wire:loading.class="opacity-60">
                <table class="w-full border-collapse text-left text-base">
                    <caption class="sr-only">Audit log entries, newest first</caption>
                    <thead class="bg-neutral-50 text-sm font-semibold text-neutral-600">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-5 py-3">When</th>
                            <th scope="col" class="px-5 py-3">Who</th>
                            <th scope="col" class="px-5 py-3">What</th>
                            <th scope="col" class="px-5 py-3">IP address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="border-t border-neutral-200 align-top">
                                <td class="whitespace-nowrap px-5 py-2.5 text-sm text-neutral-600">
                                    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('j M Y, H:i') }}</time>
                                </td>
                                <td class="px-5 py-2.5 font-medium text-neutral-900">{{ $entry->user_name ?? 'System' }}</td>
                                <td class="px-5 py-2.5 text-neutral-700">{{ $entry->description }}</td>
                                <td class="px-5 py-2.5 font-mono text-sm text-neutral-600">{{ $entry->ip_address ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-200 p-4">{{ $entries->links('pagination.studio') }}</div>
        @endif
    </x-studio.card>
</div>
