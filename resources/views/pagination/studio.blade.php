{{-- Pagination for Livewire lists in Content Studio: {{ $items->links('pagination.studio') }} --}}
@if ($paginator->hasPages())
    @php
        $button = 'inline-flex min-h-10 min-w-10 items-center justify-center gap-1 rounded-control border border-neutral-200 bg-white px-3 text-base font-medium text-neutral-900 hover:border-primary-600 hover:text-primary-700 disabled:cursor-not-allowed disabled:bg-neutral-50 disabled:text-neutral-400 disabled:hover:border-neutral-200';
        $pageName = $paginator->getPageName();
    @endphp
    <nav aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-neutral-600">
            Showing <span class="font-medium text-neutral-900">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-neutral-900">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-neutral-900">{{ $paginator->total() }}</span>
        </p>
        <div class="flex items-center gap-2">
            <button type="button" class="{{ $button }}" wire:click="previousPage('{{ $pageName }}')" @disabled($paginator->onFirstPage())>
                <x-icon name="chevron-left" class="h-4 w-4" /> Previous
            </button>
            <span class="px-1 text-sm text-neutral-600">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            <button type="button" class="{{ $button }}" wire:click="nextPage('{{ $pageName }}')" @disabled(! $paginator->hasMorePages())>
                Next <x-icon name="chevron-right" class="h-4 w-4" />
            </button>
        </div>
    </nav>
@endif
