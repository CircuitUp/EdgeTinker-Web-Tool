{{-- Content Studio layout (used by every Livewire screen). --}}
@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
    $reviewCount = $isAdmin ? \App\Models\Revision::submitted()->count() : 0;
    $pendingUsers = $isAdmin ? \App\Models\User::creators()->where('status', 'pending')->whereNotNull('email_verified_at')->count() : 0;

    $nav = [
        'Content' => array_filter([
            ['Dashboard', 'studio.dashboard', 'monitor', ['studio.dashboard'], null],
            ['Products', 'studio.products.index', 'cpu', ['studio.products.*'], null],
            ['Categories', 'studio.categories.index', 'layers', ['studio.categories.*'], null],
            ['Tags', 'studio.tags.index', 'filter', ['studio.tags.*'], null],
            Route::has('studio.pages.index') ? ['Pages', 'studio.pages.index', 'code', ['studio.pages.*'], null] : null,
            Route::has('studio.wiki.index') ? ['Wiki', 'studio.wiki.index', 'book', ['studio.wiki.*'], null] : null,
            ['Media library', 'studio.media.index', 'box', ['studio.media.*'], null],
            ['My submissions', 'studio.revisions.index', 'inbox', ['studio.revisions.*'], null],
        ]),
        'Administration' => $isAdmin ? array_filter([
            ['Review queue', 'studio.review.index', 'check-circle', ['studio.review.*'], $reviewCount],
            Route::has('studio.messages.index') ? ['Messages', 'studio.messages.index', 'mail', ['studio.messages.*'], \App\Models\ContactMessage::whereNull('read_at')->count()] : null,
            ['Users', 'studio.users.index', 'smartphone', ['studio.users.*'], $pendingUsers],
            ['Audit log', 'studio.audit.index', 'info', ['studio.audit.*'], null],
            Route::has('studio.settings') ? ['Site settings', 'studio.settings', 'cpu', ['studio.settings'], null] : null,
        ]) : [],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Content Studio' }} | Content Studio</title>
    @vite(['resources/css/studio.css', 'resources/js/studio.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-control focus:bg-primary-600 focus:px-4 focus:py-2 focus:font-semibold focus:text-white">Skip to main content</a>

    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-neutral-200 bg-white px-4 lg:hidden">
        <p class="text-lg font-bold tracking-tight">Edge<span class="text-primary-600">Tinker</span> <span class="text-sm font-semibold text-neutral-600">Studio</span></p>
        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-control hover:bg-neutral-100" @click="menu = true" aria-controls="studio-nav" :aria-expanded="menu ? 'true' : 'false'">
            <x-icon name="menu" class="h-6 w-6" /><span class="sr-only">Open menu</span>
        </button>
    </div>
    <div x-show="menu" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-neutral-900/60 lg:hidden" @click="menu = false" aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside id="studio-nav" :class="menu ? 'max-lg:translate-x-0! max-lg:visible!' : ''"
           class="on-dark fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-primary-900 text-white max-lg:invisible max-lg:-translate-x-full max-lg:transition-[transform,visibility] max-lg:duration-200">
        <div class="flex h-16 shrink-0 items-center justify-between px-5">
            <a href="{{ route('studio.dashboard') }}" class="rounded-control text-xl font-bold tracking-tight text-white">
                Edge<span class="text-primary-300">Tinker</span> <span class="text-sm font-semibold text-primary-200">Studio</span>
            </a>
            <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-control hover:bg-white/10 lg:hidden" @click="menu = false">
                <x-icon name="x" /><span class="sr-only">Close menu</span>
            </button>
        </div>

        <nav aria-label="Content Studio" class="flex-1 overflow-y-auto px-3 pb-4">
            @foreach ($nav as $group => $items)
                @continue(empty($items))
                <p class="mt-4 px-3 text-sm font-semibold uppercase tracking-wider text-primary-300">{{ $group }}</p>
                <ul class="mt-1 flex flex-col gap-0.5">
                    @foreach ($items as [$label, $route, $icon, $patterns, $count])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <li>
                            <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                               class="flex min-h-11 items-center gap-3 rounded-control px-3 text-base font-medium {{ $active ? 'bg-white/15 text-white' : 'text-primary-100 hover:bg-white/10 hover:text-white' }}">
                                <x-icon :name="$icon" class="h-5 w-5" />
                                <span class="flex-1">{{ $label }}</span>
                                @if ($count)
                                    <span class="rounded-full bg-accent px-2 text-sm font-semibold text-neutral-900">{{ $count }}<span class="sr-only"> waiting</span></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-white/15 p-3">
            <a href="{{ route('studio.account') }}" class="flex min-h-11 flex-col justify-center rounded-control px-3 py-1.5 hover:bg-white/10 {{ request()->routeIs('studio.account') ? 'bg-white/15' : '' }}">
                <span class="truncate text-base font-medium text-white">{{ $user?->name }}</span>
                <span class="text-sm text-primary-200">{{ $user?->role->label() }} · Account</span>
            </a>
            <form method="POST" action="{{ route('studio.logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-control px-3 text-base font-medium text-primary-100 hover:bg-white/10 hover:text-white">
                    <x-icon name="arrow-right" class="h-5 w-5" /> Sign out
                </button>
            </form>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="mt-1 flex min-h-11 items-center gap-3 rounded-control px-3 text-base font-medium text-primary-100 hover:bg-white/10 hover:text-white">
                <x-icon name="external" class="h-5 w-5" /> View live site <span class="sr-only">(opens in a new tab)</span>
            </a>
        </div>
    </aside>

    <main id="main" tabindex="-1" class="outline-none lg:pl-64">
        <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </div>
    </main>

    <x-toast-stack />
    @livewireScripts
</body>
</html>
