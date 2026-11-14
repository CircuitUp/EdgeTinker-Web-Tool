@props(['variant' => 'info', 'title' => null]) {{-- info | warning | danger | success --}}
@php
    $styles = [
        'info' => ['border-primary-200 bg-primary-50', 'text-primary-700', 'info'],
        'warning' => ['border-warning/30 bg-warning-50', 'text-warning', 'alert'],
        'danger' => ['border-danger/30 bg-danger-50', 'text-danger', 'x-circle'],
        'success' => ['border-success/30 bg-success-50', 'text-success', 'check-circle'],
    ][$variant];
@endphp
<div {{ $attributes->merge(['class' => 'flex gap-3 rounded-card border px-4 py-3 '.$styles[0]]) }} role="{{ in_array($variant, ['danger', 'warning']) ? 'alert' : 'status' }}">
    <x-icon :name="$styles[2]" class="mt-0.5 {{ $styles[1] }}" />
    <div class="min-w-0 text-base text-neutral-900">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
