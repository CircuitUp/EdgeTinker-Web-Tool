{{-- Favicons and app icons for every layout (site, wiki, Content Studio, error pages).
     The files are generated from the logo by `php artisan brand:favicons`. --}}
@php $v = @filemtime(public_path('favicon.ico')) ?: 1; @endphp
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $v }}" sizes="16x16 32x32 48x48">
@if (is_file(public_path('favicon.svg')))
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $v }}" type="image/svg+xml">
@endif
<link rel="icon" href="{{ asset('favicon-32x32.png') }}?v={{ $v }}" type="image/png" sizes="32x32">
<link rel="icon" href="{{ asset('favicon-16x16.png') }}?v={{ $v }}" type="image/png" sizes="16x16">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $v }}" sizes="180x180">
<link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $v }}">
<meta name="theme-color" content="{{ \App\Console\Commands\GenerateFavicons::THEME_COLOR }}">
