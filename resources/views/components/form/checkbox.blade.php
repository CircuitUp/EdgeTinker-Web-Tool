@props(['id', 'label'])
<label for="{{ $id }}" class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-base text-neutral-900">
    <input id="{{ $id }}" type="checkbox"
           {{ $attributes->merge(['name' => $id, 'value' => '1', 'class' => 'h-5 w-5 rounded-control border-neutral-300 accent-primary-600']) }}>
    <span>{{ $label }}</span>
</label>
