@props(['caption' => null, 'striped' => false])
{{--
    <x-table caption="Specifications">
        <x-slot:head><tr><th scope="col">Parameter</th><th scope="col">Value</th></tr></x-slot:head>
        <tr><th scope="row">Input</th><td>5 V</td></tr>
    </x-table>
    Scrolls sideways on narrow screens instead of breaking the page layout.
--}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-card border border-neutral-200']) }} tabindex="0" role="region" aria-label="{{ $caption ?? 'Table' }}">
    <table class="w-full border-collapse text-left text-base
                  [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3
                  [&_tbody_th]:font-medium [&_tbody_th]:text-neutral-900
                  [&_tbody_td]:text-neutral-700
                  [&_tbody_tr]:border-t [&_tbody_tr]:border-neutral-200
                  {{ $striped ? '[&_tbody_tr:nth-child(even)]:bg-neutral-50' : '' }}">
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        @isset($head)
            <thead class="bg-neutral-50 text-sm font-semibold text-neutral-600">{{ $head }}</thead>
        @endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
