@props(['title' => null, 'description' => null])
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
    <meta name="theme-color" content="#2E1065">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col bg-white text-neutral-900">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-control focus:bg-primary-600 focus:px-4 focus:py-2 focus:font-semibold focus:text-white">
        {{ __('site.nav.skip') }}
    </a>

    <x-site.header />

    <main id="main" tabindex="-1" class="flex-1 outline-none">
        {{ $slot }}
    </main>

    <x-site.footer />
    <x-toast-stack />
</body>
</html>
