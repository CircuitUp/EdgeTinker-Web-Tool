@props(['title' => null, 'description' => null, 'preview' => false, 'ogType' => 'website', 'ogImage' => null])
{{-- Public site layout: <x-layouts.site title="Page title" description="Meta description">…</x-layouts.site>
     Extra <head> tags (Open Graph, structured data) go in <x-slot:head>. --}}
@php
    $siteName = setting('site.name');
    // Custom SEO titles may already include the site name; do not append it twice.
    $fullTitle = match (true) {
        ! $title => $siteName.' – '.setting('site.tagline'),
        str_contains($title, $siteName) => $title,
        default => "$title | $siteName",
    };
    $metaDescription = $description ?? setting('site.tagline');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @include('components.favicons')

    {{-- How the page appears when its link is shared (Open Graph / social cards). --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title ?: $siteName }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col bg-white text-neutral-900">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-control focus:bg-primary-600 focus:px-4 focus:py-2 focus:font-semibold focus:text-white">
        {{ __('site.nav.skip') }}
    </a>

    @if ($preview)
        {{-- Only ever rendered inside Content Studio's preview of a pending change. --}}
        <div class="bg-accent px-4 py-2 text-center text-base font-semibold text-neutral-900" role="status">
            Preview only. This is how the page would look if the change were approved. It is not on the live site.
        </div>
    @endif

    <x-site.header />

    <main id="main" tabindex="-1" class="flex-1 outline-none">
        {{ $slot }}
    </main>

    <x-site.footer />
    <x-toast-stack />
</body>
</html>
