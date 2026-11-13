@props(['href' => null, 'selected' => false])
@php
    $classes = 'inline-flex min-h-8 items-center rounded-full px-3 py-1 text-sm font-medium transition-colors '
        .($selected
            ? 'bg-primary-600 text-white'
            : 'bg-primary-100 text-primary-800'.($href ? ' hover:bg-primary-200' : ''));
@endphp
@if ($href)
    <a href="{{ $href }}" @if ($selected) aria-current="true" @endif {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
@endif
