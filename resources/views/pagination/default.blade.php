{{-- Default paginator view (registered in AppServiceProvider). Use: {{ $paginator->links() }} --}}
@if ($paginator->hasPages())
    @php
        $link = 'inline-flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-control border border-neutral-200 bg-white px-3 text-base font-medium text-neutral-900 transition-colors hover:border-primary-600 hover:text-primary-700';
        $disabled = 'inline-flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-control border border-neutral-200 bg-neutral-50 px-3 text-base font-medium text-neutral-400';
        $current = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-control border border-primary-600 bg-primary-600 px-3 text-base font-semibold text-white';
    @endphp
    <nav aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-neutral-600">
            Showing <span class="font-medium text-neutral-900">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-neutral-900">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-neutral-900">{{ $paginator->total() }}</span>
        </p>

        <ul class="flex flex-wrap items-center gap-1.5">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $disabled }}" aria-disabled="true"><x-icon name="chevron-left" class="h-4 w-4" /><span class="sr-only sm:not-sr-only">Previous</span></span>
                @else
                    <a class="{{ $link }}" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="chevron-left" class="h-4 w-4" /><span class="sr-only sm:not-sr-only">Previous</span></a>
                @endif
            </li>

            {{-- Page numbers: hidden on small screens, where Previous / Next and the count are enough. --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden px-1 text-neutral-500 md:block" aria-hidden="true">{{ $element }}</li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="{{ $page == $paginator->currentPage() ? '' : 'hidden md:block' }}">
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $current }}" aria-current="page"><span class="sr-only">Page </span>{{ $page }}</span>
                            @else
                                <a class="{{ $link }}" href="{{ $url }}"><span class="sr-only">Page </span>{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a class="{{ $link }}" href="{{ $paginator->nextPageUrl() }}" rel="next"><span class="sr-only sm:not-sr-only">Next</span><x-icon name="chevron-right" class="h-4 w-4" /></a>
                @else
                    <span class="{{ $disabled }}" aria-disabled="true"><span class="sr-only sm:not-sr-only">Next</span><x-icon name="chevron-right" class="h-4 w-4" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
