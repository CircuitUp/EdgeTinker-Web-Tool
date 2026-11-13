@props(['items' => []]) {{-- [['label' => 'Home', 'url' => '/'], ..., ['label' => 'Current page']] --}}
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm text-neutral-600">
        @foreach ($items as $item)
            <li class="flex items-center gap-1">
                @if (! $loop->first)
                    <x-icon name="chevron-right" class="h-4 w-4 text-neutral-400" />
                @endif
                @if (! $loop->last && ! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="rounded-control px-1 py-0.5 underline-offset-2 hover:text-primary-700 hover:underline">{{ $item['label'] }}</a>
                @else
                    <span class="px-1 py-0.5 font-medium text-neutral-900" @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
