@props(['dark' => false]) {{-- dark = on a dark background (footer) --}}
{{-- Text-only wordmark until a logo is uploaded in Site settings (site.logo_path). --}}
@php $logo = media_url(setting('site.logo_path')); @endphp
<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'inline-flex min-h-11 shrink-0 items-center rounded-control']) }}>
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ setting('site.name') }}" class="h-8 w-auto">
    @else
        <span class="text-2xl font-bold tracking-tight {{ $dark ? 'text-white' : 'text-neutral-900' }}">Edge<span class="{{ $dark ? 'text-primary-300' : 'text-primary-600' }}">Tinker</span></span>
    @endif
    <span class="sr-only"> – {{ __('site.nav.home') }}</span>
</a>
