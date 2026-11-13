@props(['label', 'for', 'hint' => null, 'error' => null, 'required' => false])
{{-- Wraps a control with its label, optional hint and validation error.
     The control should set aria-describedby="{for}-hint {for}-error" (the input components do this). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $for }}" class="text-sm font-medium text-neutral-900">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">(required)</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p id="{{ $for }}-hint" class="text-sm text-neutral-600">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $for }}-error" class="flex items-center gap-1.5 text-sm font-medium text-danger">
            <x-icon name="alert" class="h-4 w-4" />{{ $error }}
        </p>
    @endif
</div>
