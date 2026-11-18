@php
    $btn = 'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control border border-neutral-300 bg-white px-3 text-sm font-semibold text-neutral-800 hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40';
    $icon = 'inline-flex h-9 w-9 items-center justify-center rounded-control text-neutral-700 hover:bg-neutral-100 disabled:opacity-30';
    $seg = 'min-h-9 px-3 text-sm font-semibold';
    $label = 'block text-sm font-medium text-neutral-800';
    $input = 'mt-1 block min-h-10 w-full rounded-control border border-neutral-500 bg-white px-2.5 text-sm text-neutral-900';
    $num = 'mt-1 block min-h-10 w-full rounded-control border border-neutral-500 bg-white px-2 text-sm font-mono text-neutral-900';
    $section = 'border-t border-neutral-200 px-4 py-4';
    $h3 = 'text-sm font-semibold uppercase tracking-wide text-neutral-600';
@endphp
<div x-data="bannerEditor(@js($boot))" wire:ignore class="banner-editor flex flex-col gap-3">

    {{-- ── Heading and status ──────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('studio.pages.index') }}" class="inline-flex min-h-9 items-center gap-1 text-sm font-semibold text-primary-700 hover:underline">
                <x-icon name="chevron-left" class="h-4 w-4" /> Pages
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900">Home banner</h1>
            <p class="text-sm text-neutral-600">
                Drag text and buttons on the banner, style them on the right. The live site does not change until {{ $isAdmin ? 'you publish.' : 'the Content Admin approves your changes.' }}
                <span x-show="dirty" x-cloak class="font-semibold text-warning">· Unsaved changes</span>
            </p>
        </div>
        @if ($revision?->needs_rebase)
            <x-studio.alert variant="warning" title="The live banner changed while you were editing">{{ $revision->rebase_note }}</x-studio.alert>
        @endif
        @if ($revision?->status === \App\Enums\RevisionStatus::Rejected)
            <x-studio.alert variant="danger" title="The Content Admin asked for changes">{{ $revision->comment }}</x-studio.alert>
        @endif
    </div>

    {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2 rounded-card border border-neutral-200 bg-white p-2 shadow-card" role="toolbar" aria-label="Banner editor tools">
        <div class="inline-flex overflow-hidden rounded-control border border-neutral-300" role="radiogroup" aria-label="Screen size">
            <template x-for="b in breakpoints" :key="b">
                <button type="button" role="radio" :aria-checked="bp === b ? 'true' : 'false'" @click="bp = b"
                        class="{{ $seg }}" :class="bp === b ? 'bg-primary-600 text-white' : 'bg-white text-neutral-800 hover:bg-neutral-50'"
                        :title="`${bpLabels[b]} (${ {desktop: '1024 px and wider', tablet: '640–1023 px', mobile: 'narrower than 640 px'}[b] })`" x-text="bpLabels[b]"></button>
            </template>
        </div>

        <div class="flex items-center">
            <button type="button" class="{{ $icon }}" @click="undo()" :disabled="!past.length && JSON.stringify(config) === snapshot" title="Undo (Ctrl+Z)">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-4"/></svg><span class="sr-only">Undo</span>
            </button>
            <button type="button" class="{{ $icon }}" @click="redo()" :disabled="!future.length" title="Redo (Shift+Ctrl+Z)">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H9a5 5 0 0 0 0 10h4"/></svg><span class="sr-only">Redo</span>
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-1" x-data="{ gridMenu: false }">
            @foreach (['grid' => 'Grid', 'guides' => 'Guides', 'snap' => 'Snap', 'rulers' => 'Rulers'] as $key => $name)
                <button type="button" class="{{ $seg }} rounded-control border" :aria-pressed="show.{{ $key }} ? 'true' : 'false'"
                        :class="show.{{ $key }} ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-neutral-300 bg-white text-neutral-700'"
                        @click="show.{{ $key }} = !show.{{ $key }}; $nextTick(() => fit())"
                        @if ($key === 'snap') title="Hold Alt while dragging to place freely" @endif>{{ $name }}</button>
            @endforeach
            <div class="relative">
                <button type="button" class="{{ $icon }}" @click="gridMenu = !gridMenu" :aria-expanded="gridMenu ? 'true' : 'false'" title="Grid settings">
                    <x-icon name="filter" class="h-4 w-4" /><span class="sr-only">Grid settings</span>
                </button>
                <div x-show="gridMenu" x-cloak @click.outside="gridMenu = false" class="absolute left-0 top-11 z-40 w-60 rounded-card border border-neutral-200 bg-white p-3 shadow-overlay">
                    <label class="{{ $label }}" for="be-grid-size">Grid size (px)</label>
                    <select id="be-grid-size" class="{{ $input }}" x-model.number="grid.size">
                        @foreach ([8, 16, 24, 32, 48, 64] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                    </select>
                    <label class="{{ $label }} mt-2" for="be-grid-custom">Custom size</label>
                    <input id="be-grid-custom" type="number" min="4" max="200" class="{{ $num }}" x-model.number="grid.size">
                    <label class="{{ $label }} mt-2" for="be-grid-color">Line colour</label>
                    <input id="be-grid-color" type="color" class="mt-1 h-10 w-full rounded-control border border-neutral-500" x-model="grid.color">
                    <label class="{{ $label }} mt-2" for="be-grid-opacity">Opacity <span x-text="Math.round(grid.opacity * 100) + '%'"></span></label>
                    <input id="be-grid-opacity" type="range" min="0.05" max="1" step="0.05" class="w-full" x-model.number="grid.opacity">
                    <button type="button" class="{{ $btn }} mt-3 w-full" @click="clearGuides()">Remove my guides on this screen size</button>
                </div>
            </div>
        </div>

        <label class="sr-only" for="be-zoom">Zoom</label>
        <select id="be-zoom" class="min-h-9 rounded-control border border-neutral-300 bg-white px-2 text-sm" x-model="zoom" @change="zoom = zoom === 'fit' ? 'fit' : +zoom">
            <option value="fit">Fit</option>
            <option value="0.5">50%</option>
            <option value="1">100%</option>
        </select>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <button type="button" class="{{ $btn }}" @click="resetToDefault()">Reset to default</button>
            <button type="button" class="{{ $btn }}" @click="preview()" title="Opens the home page with this banner in a new tab">
                Preview <x-icon name="external" class="h-4 w-4" /><span class="sr-only">(opens in a new tab)</span>
            </button>
            <button type="button" class="{{ $btn }}" @click="save(false)" :disabled="saving">Save draft</button>
            <button type="button" class="inline-flex min-h-10 items-center justify-center rounded-control bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                    @click="save(true)" :disabled="saving">{{ $isAdmin ? 'Publish' : 'Submit for approval' }}</button>
        </div>
    </div>

    {{-- Save errors --}}
    <div x-show="errors.length" x-cloak class="rounded-card border border-danger bg-danger-50 p-3" role="alert">
        <p class="text-sm font-semibold text-danger">The banner was not saved. Fix these first:</p>
        <ul class="mt-1 flex flex-col gap-1 text-sm text-neutral-900">
            <template x-for="error in errors" :key="error.key">
                <li><button type="button" class="text-left underline" @click="goToError(error.key)" x-text="error.message"></button></li>
            </template>
        </ul>
    </div>

    {{-- ── Slides ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2" aria-label="Slides">
        <div class="flex flex-wrap gap-1" role="tablist" aria-label="Slides">
            <template x-for="(slide, i) in config.slides" :key="slide.id">
                <button type="button" role="tab" :aria-selected="slideIndex === i ? 'true' : 'false'" @click="selectSlide(i)"
                        class="min-h-9 max-w-52 truncate rounded-control border px-3 text-sm font-semibold"
                        :class="slideIndex === i ? 'border-primary-600 bg-primary-600 text-white' : 'border-neutral-300 bg-white text-neutral-800 hover:bg-neutral-50'"
                        x-text="`${i + 1}. ${slide.name}`"></button>
            </template>
        </div>
        <button type="button" class="{{ $btn }}" @click="addSlide()" :disabled="config.slides.length >= limits.slides">+ Add slide</button>
        <button type="button" class="{{ $icon }}" @click="moveSlide(-1)" :disabled="slideIndex === 0" title="Move slide earlier"><x-icon name="chevron-left" /><span class="sr-only">Move slide earlier</span></button>
        <button type="button" class="{{ $icon }}" @click="moveSlide(1)" :disabled="slideIndex === config.slides.length - 1" title="Move slide later"><x-icon name="chevron-right" /><span class="sr-only">Move slide later</span></button>
        <button type="button" class="{{ $btn }}" @click="duplicateSlide()" :disabled="config.slides.length >= limits.slides">Duplicate slide</button>
        <button type="button" class="{{ $btn }} text-danger" @click="removeSlide()" :disabled="config.slides.length <= 1">Delete slide</button>
    </div>

    <div class="grid gap-3 xl:grid-cols-[16rem_minmax(0,1fr)_20rem]">

        {{-- ── Layers ─────────────────────────────────────────────────────── --}}
        <section class="order-2 flex flex-col rounded-card border border-neutral-200 bg-white shadow-card xl:order-1" aria-labelledby="be-layers">
            <div class="flex items-center justify-between px-3 pt-3">
                <h2 id="be-layers" class="{{ $h3 }}">Layers</h2>
                <span class="text-sm text-neutral-600" x-text="`${s.elements.length} / ${limits.elements}`"></span>
            </div>
            <div class="flex gap-2 p-3">
                <button type="button" class="{{ $btn }} flex-1" @click="addElement('text')">+ Add Text</button>
                <button type="button" class="{{ $btn }} flex-1" @click="addElement('button')">+ Add Button</button>
            </div>
            <ol class="flex flex-col gap-1 px-2 pb-2" data-no-nudge>
                <template x-for="({ el, index }) in layers" :key="el.id">
                    <li draggable="true" @dragstart="layerDragStart($event, index)" @dragover.prevent @drop.prevent="layerDrop(index)"
                        class="rounded-control border px-1 pb-1"
                        :class="selectedId === el.id ? 'border-primary-600 bg-primary-50' : 'border-transparent hover:bg-neutral-50'">
                        <div class="flex items-center gap-1">
                            <span class="cursor-grab px-1 text-neutral-500" aria-hidden="true" title="Drag to reorder">⋮⋮</span>
                            <button type="button" class="min-h-9 min-w-0 flex-1 truncate text-left text-sm" @click="select(el.id)" :aria-pressed="selectedId === el.id ? 'true' : 'false'"
                                    :class="el.visible ? 'text-neutral-900' : 'text-neutral-500 line-through'">
                                <span class="font-semibold" x-text="el.type === 'button' ? 'Button' : (el.tag === 'p' ? 'Text' : 'Heading')"></span>
                                <span x-text="el.content || '(empty)'"></span>
                            </button>
                            <span x-show="warningsFor(el.id).length" class="px-1 text-warning" title="Has warnings: see the inspector">⚠<span class="sr-only"> has warnings</span></span>
                        </div>
                        <div class="flex items-center justify-end gap-0.5">
                            <button type="button" class="{{ $icon }} h-8 w-8" @click="el.visible = !el.visible; commit()" :title="el.visible ? 'Hide' : 'Show'" :aria-pressed="el.visible ? 'false' : 'true'">
                                <span x-show="el.visible"><x-icon name="eye" class="h-4 w-4" /></span><span x-show="!el.visible"><x-icon name="eye-off" class="h-4 w-4" /></span>
                                <span class="sr-only">Hidden</span>
                            </button>
                            <button type="button" class="{{ $icon }} h-8 w-8" @click="el.locked = !el.locked; commit()" :title="el.locked ? 'Unlock' : 'Lock'" :aria-pressed="el.locked ? 'true' : 'false'"
                                    :class="el.locked ? 'text-neutral-900' : 'text-neutral-400'">
                                <span x-show="el.locked"><x-icon name="lock" class="h-4 w-4" /></span><span x-show="!el.locked"><x-icon name="unlock" class="h-4 w-4" /></span><span class="sr-only">Locked</span>
                            </button>
                            <button type="button" class="{{ $icon }} h-8 w-8" @click="duplicate(el)" title="Duplicate" :disabled="s.elements.length >= limits.elements">
                                <x-icon name="copy" class="h-4 w-4" /><span class="sr-only">Duplicate</span>
                            </button>
                            <button type="button" class="{{ $icon }} h-8 w-8 hover:text-danger" @click="remove(el)" title="Delete">
                                <x-icon name="trash" class="h-4 w-4" /><span class="sr-only">Delete</span>
                            </button>
                            <button type="button" class="{{ $icon }} h-8 w-8" @click="moveLayer(index, index + 1)" :disabled="index === s.elements.length - 1" title="Bring forward">
                                <x-icon name="chevron-down" class="h-4 w-4 rotate-180" /><span class="sr-only">Bring forward</span>
                            </button>
                            <button type="button" class="{{ $icon }} h-8 w-8" @click="moveLayer(index, index - 1)" :disabled="index === 0" title="Send backward">
                                <x-icon name="chevron-down" class="h-4 w-4" /><span class="sr-only">Send backward</span>
                            </button>
                        </div>
                    </li>
                </template>
                <li class="flex items-center gap-1 rounded-control border px-1" :class="selectedId === 'overlay' ? 'border-primary-600 bg-primary-50' : 'border-transparent hover:bg-neutral-50'">
                    <span class="px-1 text-neutral-400" aria-hidden="true">▧</span>
                    <button type="button" class="min-h-9 flex-1 text-left text-sm" @click="select('overlay')">
                        <span class="font-semibold">Overlay</span>
                        <span class="text-neutral-600" x-text="!overlay.enabled ? 'off' : ({ solid: 'solid', 'linear-gradient': 'linear gradient', 'radial-gradient': 'radial gradient' })[overlay.mode]"></span>
                    </button>
                </li>
                <li>
                    <button type="button" class="min-h-9 w-full rounded-control border px-2 text-left text-sm"
                            :class="selectedId === null ? 'border-primary-600 bg-primary-50' : 'border-transparent hover:bg-neutral-50'" @click="select(null)">
                        <span class="font-semibold">Background</span> <span class="text-neutral-600" x-text="backgroundName()"></span>
                    </button>
                </li>
            </ol>
            <p class="mt-auto border-t border-neutral-200 p-3 text-sm text-neutral-600">
                Top of the list is drawn on top. Screen readers read the banner from the bottom of the list up. Keys: arrows move 1 px (Shift: 10 px), Delete removes, Ctrl+D duplicates, Esc deselects.
            </p>
        </section>

        {{-- ── Canvas ─────────────────────────────────────────────────────── --}}
        <section class="order-1 min-w-0 xl:order-2" aria-label="Banner canvas">
            <div x-ref="stage" class="relative overflow-auto rounded-card border border-neutral-200 bg-neutral-200 p-2"
                 tabindex="0" aria-label="Banner canvas. Choose an element in Layers, then use the arrow keys to move it.">
                <div class="relative inline-block align-top" :style="show.rulers ? 'padding: 24px 0 0 24px' : ''">

                    {{-- Rulers: drag from them to create a guide; drag a guide back onto a ruler to remove it. --}}
                    <template x-if="show.rulers">
                        <div>
                            <div class="absolute left-0 top-0 h-6 w-6 bg-neutral-100" aria-hidden="true"></div>
                            <div class="absolute left-6 top-0 h-6 cursor-row-resize overflow-hidden bg-neutral-100" :style="`width:${canvasWidth * scale}px`"
                                 @pointerdown="guideDown($event, 'h')" title="Drag down to add a horizontal guide">
                                <template x-for="t in rulerTicks(canvasWidth)" :key="t.at">
                                    <div class="absolute bottom-0 border-l border-neutral-500" :style="`left:${t.at * scale}px;height:${t.label !== '' ? 10 : 5}px`">
                                        <span class="absolute -top-3.5 left-0.5 font-mono text-[10px] text-neutral-700" x-text="t.label"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="absolute left-0 top-6 w-6 cursor-col-resize overflow-hidden bg-neutral-100" :style="`height:${canvasHeight * scale}px`"
                                 @pointerdown="guideDown($event, 'v')" title="Drag right to add a vertical guide">
                                <template x-for="t in rulerTicks(canvasHeight)" :key="t.at">
                                    <div class="absolute right-0 border-t border-neutral-500" :style="`top:${t.at * scale}px;width:${t.label !== '' ? 10 : 5}px`">
                                        <span class="absolute left-0.5 top-0.5 font-mono text-[9px] leading-none text-neutral-700 [writing-mode:vertical-rl] rotate-180" x-text="t.label"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="relative shadow-overlay" :style="`width:${canvasWidth * scale}px;height:${canvasHeight * scale}px`">
                        <div x-ref="canvas" class="be-canvas absolute left-0 top-0 origin-top-left" :style="`width:${canvasWidth}px;transform:scale(${scale});--be-inv:${1 / scale}`">
                            <div class="bnr on-dark" :style="bannerStyle()" @pointerdown="select(selectedId === 'overlay' ? 'overlay' : null)">
                                <div class="bnr-viewport">
                                    <div class="bnr-slide" :style="slideStyle(s)">
                                        <x-banner.slide editor />
                                    </div>
                                </div>
                            </div>

                            {{-- Editor marks, drawn above the banner (not part of it). --}}
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                <div x-show="show.grid" class="absolute inset-0" :style="gridStyle()"></div>
                                <template x-if="show.guides">
                                    <div>
                                        <div class="be-guide-center absolute inset-y-0 left-1/2"></div>
                                        <div class="be-guide-center be-guide-center--h absolute inset-x-0 top-1/2"></div>
                                        <div class="be-safe absolute" :style="`left:${area.x}px;top:8px;width:${area.w}px;bottom:8px`"></div>
                                        <template x-for="(g, i) in currentGuides.v" :key="'v' + i">
                                            <div class="be-guide pointer-events-auto absolute inset-y-0 cursor-col-resize" :style="`left:${g}px`" @pointerdown="guideDown($event, 'v', i)"></div>
                                        </template>
                                        <template x-for="(g, i) in currentGuides.h" :key="'h' + i">
                                            <div class="be-guide be-guide--h pointer-events-auto absolute inset-x-0 cursor-row-resize" :style="`top:${g}px`" @pointerdown="guideDown($event, 'h', i)"></div>
                                        </template>
                                    </div>
                                </template>
                                <template x-for="(line, i) in smart" :key="i">
                                    <div class="be-smart absolute" :class="line.axis === 'h' && 'be-smart--h'"
                                         :style="line.axis === 'v' ? `left:${line.at}px;top:${line.from}px;height:${line.to - line.from}px` : `top:${line.at}px;left:${line.from}px;width:${line.to - line.from}px`">
                                        <span class="be-smart-label" x-text="line.label"></span>
                                    </div>
                                </template>
                                <template x-if="box && selected">
                                    <div class="be-select absolute" :class="selected.locked && 'is-locked'"
                                         :style="`left:${box.cx}px;top:${box.cy}px;width:${box.w}px;height:${box.h}px;transform:translate(-50%,-50%) rotate(${box.rotation}deg)`">
                                        <template x-if="!selected.locked && !editingId">
                                            <div>
                                                <span class="be-handle be-handle--w pointer-events-auto" @pointerdown="handleDown($event, 'resize-left')" title="Drag to change the width"></span>
                                                <span class="be-handle be-handle--e pointer-events-auto" @pointerdown="handleDown($event, 'resize-right')" title="Drag to change the width"></span>
                                                <span class="be-rotate pointer-events-auto" @pointerdown="handleDown($event, 'rotate')" title="Drag to rotate (Shift: 15° steps)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="readout" x-cloak class="pointer-events-none sticky bottom-2 left-2 mt-2 inline-block rounded-control bg-neutral-900 px-2 py-1 font-mono text-sm text-white" x-text="readout"></div>
            </div>
            <p class="mt-2 text-sm text-neutral-600">
                <span x-text="`${bpLabels[bp]}: ${canvasWidth} × ${canvasHeight} px`"></span> ·
                Double-click text to edit it. Smaller screens follow the larger one until you change something on them.
                Dashed box: the safe area, lined up with the page content.
            </p>
        </section>

        {{-- ── Inspector ──────────────────────────────────────────────────── --}}
        <aside class="order-3 rounded-card border border-neutral-200 bg-white shadow-card" aria-labelledby="be-inspector" data-no-nudge>
            <h2 id="be-inspector" class="px-4 pt-4 text-lg font-semibold text-neutral-900"
                x-text="selected ? (selected.type === 'button' ? 'Button' : 'Text') : (selectedId === 'overlay' ? 'Overlay' : 'Banner and background')"></h2>

            {{-- Element ------------------------------------------------------------------ --}}
            <template x-if="selected">
                <div>
                    @include('livewire.studio.banner.partials.element')
                </div>
            </template>

            {{-- Overlay ------------------------------------------------------------------ --}}
            <template x-if="selectedId === 'overlay'">
                <div>
                    @include('livewire.studio.banner.partials.overlay')
                </div>
            </template>

            {{-- Banner and background ----------------------------------------------------- --}}
            <template x-if="selectedId === null">
                <div>
                    @include('livewire.studio.banner.partials.background')
                </div>
            </template>

            {{-- Warnings for all screen sizes --}}
            <div class="{{ $section }}" x-show="warnings.length" x-cloak>
                <h3 class="{{ $h3 }}">Check these</h3>
                <ul class="mt-2 flex flex-col gap-1 text-sm text-neutral-900">
                    <template x-for="(w, i) in warnings" :key="i">
                        <li class="flex gap-1.5"><span class="text-warning" aria-hidden="true">⚠</span>
                            <button type="button" class="text-left underline decoration-neutral-400" @click="bp = w.bp; select(w.id)" x-text="w.text"></button></li>
                    </template>
                </ul>
            </div>
        </aside>
    </div>

    {{-- Hidden full-size copies of the slide at each screen size, used to check every breakpoint. --}}
    <div class="be-measure" aria-hidden="true" inert>
        @foreach (['desktop' => 1440, 'tablet' => 820, 'mobile' => 390] as $b => $w)
            <div x-ref="measure_{{ $b }}" style="width: {{ $w }}px">
                <div class="bnr" :style="bannerStyle()">
                    <div class="bnr-viewport">
                        <div class="bnr-slide" :style="slideStyle(s)">
                            <x-banner.slide editor />
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @include('livewire.studio.banner.partials.color-picker')
    @include('livewire.studio.banner.partials.library')
</div>
