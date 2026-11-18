@props(['title'])
{{-- Sign-in / registration pages of Content Studio. Never indexed, never linked from the public site. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | Content Studio</title>
    @include('components.favicons')
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-primary-50 text-neutral-900">
    <main class="flex flex-1 items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <p class="text-center text-2xl font-bold tracking-tight text-neutral-900">Edge<span class="text-primary-600">Tinker</span></p>
            <p class="mt-1 text-center text-sm font-semibold uppercase tracking-wider text-neutral-600">Content Studio</p>

            <div class="mt-6 rounded-panel border border-neutral-200 bg-white p-6 shadow-card sm:p-8">
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900">{{ $title }}</h1>

                @if (session('status'))
                    <p class="mt-4 rounded-control border border-primary-200 bg-primary-50 px-3 py-2 text-sm text-neutral-900" role="status">{{ session('status') }}</p>
                @endif

                <div class="mt-5">{{ $slot }}</div>
            </div>

            @isset($footer)
                <div class="mt-5 text-center text-sm text-neutral-600">{{ $footer }}</div>
            @endisset
        </div>
    </main>
</body>
</html>
