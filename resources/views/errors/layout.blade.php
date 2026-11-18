{{--
    Stand-alone page for errors. It deliberately uses no database, no compiled
    assets and no other templates, so it still renders when something
    fundamental is broken. Colours are the design-system values, written out.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | EdgeTinker</title>
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    <meta name="theme-color" content="#2E1065">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
               background: #F5F3FF; color: #0F172A; font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.5; }
        main { width: 100%; max-width: 34rem; background: #fff; border: 1px solid #E2E8F0; border-radius: 16px; padding: 2rem; text-align: center;
               box-shadow: 0 1px 2px rgb(15 23 42 / 0.06), 0 2px 8px rgb(15 23 42 / 0.06); }
        .brand { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; margin: 0; }
        .brand span { color: #6D28D9; }
        .code { margin: 1.5rem 0 0; font-family: ui-monospace, Consolas, monospace; font-weight: 600; color: #5B21B6; }
        h1 { margin: 0.25rem 0 0; font-size: 1.875rem; line-height: 2.25rem; letter-spacing: -0.02em; }
        p.text { margin: 1rem 0 0; font-size: 1.125rem; color: #475569; }
        a.button { display: inline-flex; align-items: center; justify-content: center; min-height: 2.75rem; margin-top: 1.5rem; padding: 0.5rem 1.25rem;
                   border-radius: 6px; background: #6D28D9; color: #fff; font-weight: 600; text-decoration: none; }
        a.button:hover { background: #5B21B6; }
        a.button:focus-visible { outline: 2px solid #6D28D9; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <p class="brand">Edge<span>Tinker</span></p>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="text">@yield('message')</p>
        <a class="button" href="@yield('link', '/')">@yield('link_text', 'Go to the home page')</a>
    </main>
</body>
</html>
