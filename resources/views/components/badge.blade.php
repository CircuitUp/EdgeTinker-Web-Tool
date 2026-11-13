@props(['variant' => 'neutral']) {{-- success | warning | danger | info | neutral --}}
@php
    $variants = [
        'success' => 'border-success/30 bg-success-50 text-success',
        'warning' => 'border-warning/30 bg-warning-50 text-warning',
        'danger' => 'border-danger/30 bg-danger-50 text-danger',
        'info' => 'border-primary-600/30 bg-info-50 text-info',
        'neutral' => 'border-neutral-200 bg-neutral-50 text-neutral-600',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-0.5 text-sm font-medium '.($variants[$variant] ?? $variants['neutral'])]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $slot }}
</span>
