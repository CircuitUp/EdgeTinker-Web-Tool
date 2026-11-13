@props([
    'variant' => 'primary',   // primary | secondary | ghost | danger | inverse | outline-inverse
    'size' => 'md',           // sm | md | lg
    'href' => null,           // renders an <a> when set
    'type' => 'button',
])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-700',
        'secondary' => 'border border-primary-600 bg-white text-primary-700 hover:bg-primary-50 active:bg-primary-100',
        'ghost' => 'text-primary-700 hover:bg-primary-50 active:bg-primary-100',
        'danger' => 'bg-danger text-white hover:bg-danger/90',
        'inverse' => 'bg-white text-primary-900 hover:bg-primary-50 active:bg-primary-100',
        'outline-inverse' => 'border border-white/70 text-white hover:bg-white/10 active:bg-white/20',
    ];

    $sizes = [
        'sm' => 'min-h-9 px-3 py-1.5 text-sm',
        'md' => 'min-h-11 px-5 py-2 text-base',
        'lg' => 'min-h-12 px-6 py-3 text-lg',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
