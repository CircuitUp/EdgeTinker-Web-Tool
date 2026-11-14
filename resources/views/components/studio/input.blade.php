@props(['label', 'model', 'type' => 'text', 'hint' => null, 'required' => false, 'mono' => false, 'live' => false])
{{-- A labelled input bound to a Livewire property, with its validation error. model="form.name" --}}
@php
    $id = 'f-'.str_replace(['.', '_'], '-', $model);
    $invalid = $errors->has($model);
@endphp
<x-form.field :label="$label" :for="$id" :hint="$hint" :required="$required" :error="$errors->first($model)" {{ $attributes->only('class') }}>
    <input id="{{ $id }}" type="{{ $type }}" name="{{ $model }}"
           wire:model{{ $live ? '.live.debounce.400ms' : '' }}="{{ $model }}"
           aria-describedby="{{ $id }}-hint {{ $id }}-error" @if ($invalid) aria-invalid="true" @endif
           {{ $attributes->except('class')->class([
               'block min-h-11 w-full rounded-control border bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500',
               'disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-500',
               'font-mono' => $mono,
               'border-danger' => $invalid,
               'border-neutral-300 hover:border-neutral-400' => ! $invalid,
           ]) }}>
</x-form.field>
