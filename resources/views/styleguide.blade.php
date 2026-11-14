{{-- Design system reference. Local and staging only (route is not registered in production). --}}
@php
    $primary = [
        50 => ['#F5F3FF', 'Tinted section backgrounds', true],
        100 => ['#EDE9FE', 'Tag chips, selected rows', true],
        200 => ['#DDD6FE', 'Interpolated', false],
        300 => ['#C4B5FD', 'Interpolated', false],
        400 => ['#A78BFA', 'Interpolated', false],
        500 => ['#8B5CF6', 'Interpolated', false],
        600 => ['#6D28D9', 'Buttons, links, active states', true],
        700 => ['#5B21B6', 'Hover and pressed', true],
        800 => ['#451A8E', 'Interpolated', false],
        900 => ['#2E1065', 'Footer, hero overlay, wiki top bar', true],
    ];
    $neutral = [
        50 => ['#F8FAFC', 'Page background alternates', true],
        100 => ['#F1F5F9', 'Interpolated', false],
        200 => ['#E2E8F0', 'Borders and dividers', true],
        300 => ['#CBD5E1', 'Dashed outlines, hover dividers', false],
        400 => ['#94A3B8', 'Disabled text, icons', false],
        500 => ['#64748B', 'Input borders (3:1 contrast), placeholder text', false],
        600 => ['#475569', 'Secondary text', true],
        700 => ['#334155', 'Interpolated', false],
        800 => ['#1E293B', 'Interpolated', false],
        900 => ['#0F172A', 'Headings and body text', true],
    ];
    $status = [
        'success' => ['#15803D', 'In stock'],
        'warning' => ['#B45309', 'Low stock'],
        'danger' => ['#B91C1C', 'Out of stock, errors'],
        'info' => ['Primary 600', 'Coming soon'],
        'accent' => ['#F59E0B', 'Sparing highlights only'],
    ];
    $type = [
        ['text-5xl', '48 px', 'font-bold tracking-tight'],
        ['text-4xl', '36 px', 'font-bold tracking-tight'],
        ['text-3xl', '30 px', 'font-bold tracking-tight'],
        ['text-2xl', '24 px', 'font-semibold'],
        ['text-xl', '20 px', 'font-semibold'],
        ['text-lg', '18 px', ''],
        ['text-base', '16 px', ''],
        ['text-sm', '14 px', ''],
    ];
    $nav = [
        'colour' => 'Colour', 'type' => 'Type', 'spacing' => 'Spacing, radius, shadow', 'buttons' => 'Buttons',
        'badges' => 'Badges and chips', 'product-card' => 'Product card', 'breadcrumb' => 'Breadcrumb', 'tabs' => 'Tabs',
        'table' => 'Table', 'forms' => 'Form fields', 'pagination' => 'Pagination', 'toast' => 'Toast',
        'modal' => 'Modal', 'empty-state' => 'Empty state', 'icons' => 'Icons',
    ];
    $icons = ['search', 'menu', 'x', 'chevron-down', 'chevron-right', 'chevron-left', 'arrow-right', 'external', 'mail', 'phone', 'map-pin',
        'cpu', 'layers', 'code', 'box', 'monitor', 'smartphone', 'check', 'pause', 'play', 'info', 'alert', 'check-circle', 'x-circle',
        'inbox', 'filter', 'book', 'linkedin', 'github', 'youtube', 'facebook'];
    $h2 = 'text-2xl font-bold tracking-tight text-neutral-900';
    $h3 = 'mt-8 text-lg font-semibold text-neutral-900';
    $note = 'mt-1 text-base text-neutral-600';
    $code = 'rounded-control bg-neutral-100 px-1.5 py-0.5 font-mono text-sm text-neutral-800';
@endphp
<x-layouts.site title="Styleguide">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="border-b border-neutral-200 bg-primary-50">
        <div class="container-page py-10">
            <x-breadcrumb :items="[['label' => 'Home', 'url' => route('home')], ['label' => 'Styleguide']]" />
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-neutral-900">EdgeTinker design system</h1>
            <p class="mt-2 max-w-3xl text-lg text-neutral-600">
                Every token and component used on the public site and in Content Studio. Tokens are defined once in
                <code class="{{ $code }}">resources/css/app.css</code>; components live in
                <code class="{{ $code }}">resources/views/components</code>. This page is not available in production.
            </p>
        </div>
    </div>

    <div class="container-page grid gap-10 py-10 lg:grid-cols-[14rem_1fr]">
        <nav aria-label="Styleguide sections" class="lg:sticky lg:top-24 lg:self-start">
            <ul class="flex flex-wrap gap-1 lg:flex-col">
                @foreach ($nav as $anchor => $label)
                    <li><a href="#{{ $anchor }}" class="inline-flex min-h-9 items-center rounded-control px-3 text-sm font-medium text-neutral-600 hover:bg-primary-50 hover:text-primary-700">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="flex min-w-0 flex-col gap-16 [&>section]:scroll-mt-28">

            {{-- Colour --}}
            <section id="colour">
                <h2 class="{{ $h2 }}">Colour</h2>
                <p class="{{ $note }}">Swatches marked <x-badge variant="info">approved</x-badge> are the values from the brief; the others are filled in evenly between them.</p>

                @foreach (['Primary' => ['primary', $primary], 'Neutral' => ['neutral', $neutral]] as $groupName => [$prefix, $scale])
                    <h3 class="{{ $h3 }}">{{ $groupName }}</h3>
                    <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                        @foreach ($scale as $step => [$hex, $use, $approved])
                            <li class="overflow-hidden rounded-card border border-neutral-200 bg-white">
                                <div class="h-16" style="background-color: var(--et-{{ $prefix }}-{{ $step }})"></div>
                                <div class="p-3">
                                    <p class="text-sm font-semibold text-neutral-900">{{ $groupName }} {{ $step }}</p>
                                    <p class="font-mono text-sm text-neutral-600">{{ $hex }}</p>
                                    <p class="mt-1 text-sm text-neutral-600">{{ $use }}</p>
                                    @if ($approved)<x-badge variant="info" class="mt-2">approved</x-badge>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach

                <h3 class="{{ $h3 }}">Status and accent</h3>
                <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    @foreach ($status as $name => [$hex, $use])
                        <li class="overflow-hidden rounded-card border border-neutral-200 bg-white">
                            <div class="h-16" style="background-color: var(--et-{{ $name }})"></div>
                            <div class="p-3">
                                <p class="text-sm font-semibold capitalize text-neutral-900">{{ $name }}</p>
                                <p class="font-mono text-sm text-neutral-600">{{ $hex }}</p>
                                <p class="mt-1 text-sm text-neutral-600">{{ $use }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Type --}}
            <section id="type">
                <h2 class="{{ $h2 }}">Type</h2>
                <p class="{{ $note }}">Inter for interface and body text; JetBrains Mono for SKUs, specification values and code. Both are self-hosted.</p>
                <dl class="mt-6 flex flex-col divide-y divide-neutral-200 border-y border-neutral-200">
                    @foreach ($type as [$class, $px, $weight])
                        <div class="flex flex-col gap-1 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                            <dt class="w-40 shrink-0 font-mono text-sm text-neutral-600">{{ $class }} · {{ $px }}</dt>
                            <dd class="{{ $class }} {{ $weight }} min-w-0 break-words text-neutral-900">Embedded hardware, engineered</dd>
                        </div>
                    @endforeach
                </dl>
                <h3 class="{{ $h3 }}">Monospace</h3>
                <p class="mt-2 font-mono text-base text-neutral-900">ET-DEV-S3-01 · 7–36 V DC · 500 kHz · <span class="text-primary-700">digitalWrite(LED, HIGH);</span></p>
            </section>

            {{-- Spacing, radius, shadow --}}
            <section id="spacing">
                <h2 class="{{ $h2 }}">Spacing, radius and shadow</h2>
                <h3 class="{{ $h3 }}">Spacing (4 px base)</h3>
                <ul class="mt-3 flex flex-col gap-2">
                    @foreach ([1 => 'w-1', 2 => 'w-2', 3 => 'w-3', 4 => 'w-4', 6 => 'w-6', 8 => 'w-8', 12 => 'w-12', 16 => 'w-16', 24 => 'w-24'] as $step => $width)
                        <li class="flex items-center gap-4">
                            <span class="w-24 font-mono text-sm text-neutral-600">{{ $step }} · {{ $step * 4 }} px</span>
                            <span class="{{ $width }} h-4 rounded-[2px] bg-primary-600"></span>
                        </li>
                    @endforeach
                </ul>

                <h3 class="{{ $h3 }}">Radius</h3>
                <ul class="mt-3 grid gap-4 sm:grid-cols-3">
                    <li class="rounded-control border border-neutral-300 bg-white p-5"><p class="font-semibold">6 px · rounded-control</p><p class="text-sm text-neutral-600">Inputs, buttons</p></li>
                    <li class="rounded-card border border-neutral-300 bg-white p-5"><p class="font-semibold">10 px · rounded-card</p><p class="text-sm text-neutral-600">Cards</p></li>
                    <li class="rounded-panel border border-neutral-300 bg-white p-5"><p class="font-semibold">16 px · rounded-panel</p><p class="text-sm text-neutral-600">Panels, modals</p></li>
                </ul>

                <h3 class="{{ $h3 }}">Shadow</h3>
                <ul class="mt-3 grid gap-6 rounded-card bg-neutral-50 p-6 sm:grid-cols-2">
                    <li class="rounded-card bg-white p-5 shadow-card"><p class="font-semibold">shadow-card</p><p class="text-sm text-neutral-600">Cards at rest</p></li>
                    <li class="rounded-card bg-white p-5 shadow-overlay"><p class="font-semibold">shadow-overlay</p><p class="text-sm text-neutral-600">Menus, modals, toasts, card hover</p></li>
                </ul>
            </section>

            {{-- Buttons --}}
            <section id="buttons">
                <h2 class="{{ $h2 }}">Buttons</h2>
                <p class="{{ $note }}"><code class="{{ $code }}">&lt;x-button variant="…" size="…" href="…"&gt;</code>. With <code class="{{ $code }}">href</code> it renders a link.</p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <x-button>Primary</x-button>
                    <x-button variant="secondary">Secondary</x-button>
                    <x-button variant="ghost">Ghost</x-button>
                    <x-button variant="danger">Danger</x-button>
                    <x-button disabled>Disabled</x-button>
                    <x-button href="#buttons">Link button <x-icon name="arrow-right" class="h-4 w-4" /></x-button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-button size="sm">Small</x-button>
                    <x-button size="md">Medium</x-button>
                    <x-button size="lg">Large</x-button>
                </div>
                <div class="on-dark mt-4 flex flex-wrap items-center gap-3 rounded-card bg-primary-900 p-5">
                    <x-button variant="inverse">Inverse</x-button>
                    <x-button variant="outline-inverse">Outline inverse</x-button>
                    <span class="text-sm text-primary-100">For dark (Primary 900) surfaces</span>
                </div>
            </section>

            {{-- Badges and chips --}}
            <section id="badges">
                <h2 class="{{ $h2 }}">Badges and tag chips</h2>
                <h3 class="{{ $h3 }}">Stock badges</h3>
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach (\App\Enums\StockStatus::cases() as $stockStatus)
                        <x-stock-badge :status="$stockStatus" />
                    @endforeach
                    <x-badge>Neutral</x-badge>
                </div>
                <h3 class="{{ $h3 }}">Tag chips</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-tag-chip href="#badges">ESP32</x-tag-chip>
                    <x-tag-chip href="#badges">Wi-Fi</x-tag-chip>
                    <x-tag-chip href="#badges" selected>USB-C (selected)</x-tag-chip>
                    <x-tag-chip>Not a link</x-tag-chip>
                </div>
            </section>

            {{-- Product card --}}
            <section id="product-card">
                <h2 class="{{ $h2 }}">Product card</h2>
                <p class="{{ $note }}"><code class="{{ $code }}">&lt;x-product-card :product="$product" /&gt;</code>. One card everywhere. The price follows the currency toggle in the header.</p>
                @if ($products->isNotEmpty())
                    <ul class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            <li><x-product-card :product="$product" /></li>
                        @endforeach
                    </ul>
                @else
                    <x-empty-state class="mt-6" title="No products seeded">Run <code class="{{ $code }}">php artisan db:seed</code> to load the demo catalogue.</x-empty-state>
                @endif
            </section>

            {{-- Breadcrumb --}}
            <section id="breadcrumb">
                <h2 class="{{ $h2 }}">Breadcrumb</h2>
                <x-breadcrumb class="mt-4" :items="[
                    ['label' => 'Home', 'url' => '#breadcrumb'],
                    ['label' => 'Products', 'url' => '#breadcrumb'],
                    ['label' => 'Development Boards', 'url' => '#breadcrumb'],
                    ['label' => 'EdgeCore ESP32-S3 Dev Board'],
                ]" />
            </section>

            {{-- Tabs --}}
            <section id="tabs">
                <h2 class="{{ $h2 }}">Tabs</h2>
                <p class="{{ $note }}">Arrow keys move between tabs; Home and End jump to the ends.</p>
                <x-tabs id="demo" class="mt-4" label="Example tabs" :tabs="['overview' => 'Overview', 'specs' => 'Specifications', 'docs' => 'Documents & Downloads']">
                    <x-slot:overview><p class="text-base text-neutral-700">Overview panel content.</p></x-slot:overview>
                    <x-slot:specs><p class="text-base text-neutral-700">Specifications panel content.</p></x-slot:specs>
                    <x-slot:docs><p class="text-base text-neutral-700">Documents panel content.</p></x-slot:docs>
                </x-tabs>
            </section>

            {{-- Table --}}
            <section id="table">
                <h2 class="{{ $h2 }}">Table</h2>
                <x-table class="mt-4" caption="Example specifications" striped>
                    <x-slot:head>
                        <tr><th scope="col">Parameter</th><th scope="col">Value</th></tr>
                    </x-slot:head>
                    <tr><th scope="row">Input voltage</th><td class="font-mono">7–36 V DC</td></tr>
                    <tr><th scope="row">Output</th><td class="font-mono">5 V, 3 A</td></tr>
                    <tr><th scope="row">Switching frequency</th><td class="font-mono">500 kHz</td></tr>
                    <tr><th scope="row">Dimensions</th><td class="font-mono">28 × 20 mm</td></tr>
                </x-table>
            </section>

            {{-- Forms --}}
            <section id="forms">
                <h2 class="{{ $h2 }}">Form fields</h2>
                <form class="mt-6 grid gap-5 sm:grid-cols-2" onsubmit="return false">
                    <x-form.field label="Product name" for="sg-name" required hint="Shown on cards and the product page.">
                        <x-form.input id="sg-name" placeholder="EdgeCore ESP32-S3 Dev Board" />
                    </x-form.field>
                    <x-form.field label="SKU" for="sg-sku" error="This SKU is already in use.">
                        <x-form.input id="sg-sku" value="ET-DEV-S3-01" mono invalid />
                    </x-form.field>
                    <x-form.field label="Category" for="sg-category">
                        <x-form.select id="sg-category">
                            <option>Development Boards</option>
                            <option>Sensor Boards</option>
                        </x-form.select>
                    </x-form.field>
                    <x-form.field label="Disabled" for="sg-disabled">
                        <x-form.input id="sg-disabled" value="Cannot be edited" disabled />
                    </x-form.field>
                    <x-form.field label="Short description" for="sg-description" class="sm:col-span-2">
                        <x-form.textarea id="sg-description" rows="3" placeholder="One line shown on product cards."></x-form.textarea>
                    </x-form.field>
                    <x-form.checkbox id="sg-featured" label="Feature on the home page" checked />
                </form>
            </section>

            {{-- Pagination --}}
            <section id="pagination">
                <h2 class="{{ $h2 }}">Pagination</h2>
                <p class="{{ $note }}"><code class="{{ $code }}">@{{ $paginator->links() }}</code>. Page numbers collapse to Previous / Next on small screens.</p>
                <div class="mt-4">{{ $paginator->links() }}</div>
            </section>

            {{-- Toast --}}
            <section id="toast" x-data>
                <h2 class="{{ $h2 }}">Toast</h2>
                <p class="{{ $note }}">Appears bottom-right, announced politely to screen readers, dismisses itself after 5 seconds.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <x-button variant="secondary" @click="$store.toasts.push('success', 'Product submitted for approval.')">Success</x-button>
                    <x-button variant="secondary" @click="$store.toasts.push('error', 'Something went wrong. Please try again.')">Error</x-button>
                    <x-button variant="secondary" @click="$store.toasts.push('warning', 'This product has a pending revision.')">Warning</x-button>
                    <x-button variant="secondary" @click="$store.toasts.push('info', 'Prices are now shown in LKR.')">Info</x-button>
                </div>
            </section>

            {{-- Modal --}}
            <section id="modal" x-data>
                <h2 class="{{ $h2 }}">Modal</h2>
                <p class="{{ $note }}">Traps focus while open; closes with Escape, the close button or the backdrop.</p>
                <x-button class="mt-4" @click="$dispatch('open-modal', 'styleguide-demo')">Open modal</x-button>
                <x-modal name="styleguide-demo" title="Reject submission">
                    <p>Tell the creator what needs to change. They will see this comment with the returned draft.</p>
                    <x-form.field label="Comment" for="sg-comment" class="mt-4">
                        <x-form.textarea id="sg-comment" rows="3"></x-form.textarea>
                    </x-form.field>
                    <x-slot:footer>
                        <x-button variant="ghost" @click="$dispatch('close-modal', 'styleguide-demo')">Cancel</x-button>
                        <x-button variant="danger" @click="$dispatch('close-modal', 'styleguide-demo')">Reject</x-button>
                    </x-slot:footer>
                </x-modal>
            </section>

            {{-- Empty state --}}
            <section id="empty-state">
                <h2 class="{{ $h2 }}">Empty state</h2>
                <x-empty-state class="mt-4" icon="search" title="No products match your filters">
                    Try removing a filter or searching for a different term.
                    <x-slot:action><x-button variant="secondary">Clear filters</x-button></x-slot:action>
                </x-empty-state>
            </section>

            {{-- Icons --}}
            <section id="icons">
                <h2 class="{{ $h2 }}">Icons</h2>
                <p class="{{ $note }}"><code class="{{ $code }}">&lt;x-icon name="…" /&gt;</code></p>
                <ul class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5 xl:grid-cols-6">
                    @foreach ($icons as $icon)
                        <li class="flex flex-col items-center gap-2 rounded-card border border-neutral-200 p-3 text-neutral-900">
                            <x-icon :name="$icon" class="h-6 w-6" />
                            <span class="break-all text-center font-mono text-sm text-neutral-600">{{ $icon }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</x-layouts.site>
