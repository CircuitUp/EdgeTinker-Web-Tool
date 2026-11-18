{{-- Inspector: the selected text or button. `selected` is the element; values marked "per screen size" follow the breakpoint switch. --}}
@php
    $swatch = 'inline-flex h-10 w-full items-center gap-2 rounded-control border border-neutral-500 bg-white px-2 text-sm text-neutral-900';
    $chip = 'h-6 w-6 shrink-0 rounded border border-neutral-300 be-checker';
    $toggle = 'min-h-9 min-w-9 rounded-control border px-2 text-sm font-semibold';
    $bpTag = 'ml-1 rounded bg-primary-100 px-1.5 text-xs font-semibold text-primary-800';
@endphp

{{-- Content --}}
<div class="{{ $section }}">
    <label class="{{ $label }}" for="be-content" x-text="selected.type === 'button' ? 'Button text' : 'Text'"></label>
    <textarea id="be-content" rows="3" class="{{ $input }} py-2" :maxlength="selected.type === 'button' ? 60 : 300" x-model="selected.content"></textarea>
    <p class="mt-1 text-sm text-neutral-600" x-show="selected.type === 'text'">Line breaks are kept. Tip: double-click the text on the banner to type directly.</p>

    <template x-if="selected.type === 'text'">
        <div class="mt-3">
            <label class="{{ $label }}" for="be-tag">Kind of text</label>
            <select id="be-tag" class="{{ $input }}" x-model="selected.tag">
                <option value="h2">Heading (h2)</option>
                <option value="h3">Sub-heading (h3)</option>
                <option value="p">Paragraph</option>
            </select>
            <p class="mt-1 text-sm text-neutral-600">Only affects how search engines and screen readers understand it. The page already has its main heading (h1).</p>
        </div>
    </template>

    <template x-if="selected.type === 'button'">
        <div class="mt-3 flex flex-col gap-3">
            <div>
                <label class="{{ $label }}" for="be-href">Link</label>
                <input id="be-href" type="text" class="{{ $input }} font-mono" maxlength="500" placeholder="/products or https://…" x-model.lazy="selected.href">
                <p class="mt-1 text-sm text-neutral-600">A page on this site (starting with /), a full https:// address, #section, mailto: or tel:.</p>
            </div>
            <label class="inline-flex min-h-10 items-center gap-2 text-sm text-neutral-900"><input type="checkbox" class="h-5 w-5 accent-primary-600" x-model="selected.newTab"> Open in a new tab</label>
            <div>
                <label class="{{ $label }}" for="be-icon">Icon</label>
                <select id="be-icon" class="{{ $input }}" x-model="selected.icon">
                    <option value="none">None</option>
                    <option value="arrow-right">Arrow</option>
                    <option value="external">External link</option>
                </select>
            </div>
        </div>
    </template>
</div>

{{-- Typography --}}
<div class="{{ $section }}">
    <h3 class="{{ $h3 }}">Text style</h3>

    <div class="relative mt-2">
        <span class="{{ $label }}" id="be-font-label">Font</span>
        <button type="button" class="{{ $input }} flex items-center justify-between text-left" aria-haspopup="listbox" :aria-expanded="fontMenu ? 'true' : 'false'" aria-labelledby="be-font-label"
                @click="fontMenu ? fontMenu = false : openFontMenu()" :style="`font-family:${font(selected.typography.fontFamily).stack}`">
            <span x-text="font(selected.typography.fontFamily).label"></span><x-icon name="chevron-down" class="h-4 w-4" />
        </button>
        <ul x-show="fontMenu" x-cloak @click.outside="fontMenu = false" @keydown.escape.stop="fontMenu = false" role="listbox" aria-labelledby="be-font-label"
            class="absolute z-40 mt-1 max-h-80 w-full overflow-auto rounded-card border border-neutral-200 bg-white py-1 shadow-overlay">
            <template x-for="f in fonts" :key="f.key">
                <li role="option" :aria-selected="selected.typography.fontFamily === f.key ? 'true' : 'false'">
                    <button type="button" class="flex min-h-10 w-full items-center justify-between px-3 text-left text-base hover:bg-primary-50"
                            :class="selected.typography.fontFamily === f.key && 'bg-primary-50 font-semibold'" :style="`font-family:${f.stack}`" @click="chooseFont(f.key)">
                        <span x-text="f.label"></span>
                        <span class="font-sans text-xs text-neutral-500" x-text="f.google ? (fontsLoaded[`${f.key}|${f.weights.includes(400) ? 400 : f.weights[0]}|false`] === 'loading' ? 'loading…' : 'Google') : ''"></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3">
        <div>
            <label class="{{ $label }}" for="be-weight">Weight</label>
            <select id="be-weight" class="{{ $input }}" x-model.number="selected.typography.fontWeight" @change="typographyChanged()">
                <template x-for="w in font(selected.typography.fontFamily).weights" :key="w">
                    <option :value="w" :selected="selected.typography.fontWeight === w" x-text="({100:'Thin',200:'Extra light',300:'Light',400:'Regular',500:'Medium',600:'Semibold',700:'Bold',800:'Extra bold',900:'Black'})[w] + ' ' + w"></option>
                </template>
            </select>
        </div>
        <div>
            <label class="{{ $label }}" for="be-transform">Case</label>
            <select id="be-transform" class="{{ $input }}" x-model="selected.typography.textTransform">
                <option value="none">As typed</option><option value="uppercase">UPPERCASE</option><option value="lowercase">lowercase</option><option value="capitalize">Capitalised</option>
            </select>
        </div>
        <div>
            <label class="{{ $label }}" for="be-size">Size (px) <span class="{{ $bpTag }}" x-text="bpLabels[bp]"></span></label>
            <input id="be-size" type="number" min="8" max="200" step="1" class="{{ $num }}" :value="bpValue(selected.typography.fontSize)"
                   @change="bpSet(selected.typography.fontSize, $event.target.value, 8, 200)">
            <p class="mt-0.5 text-xs text-neutral-600" x-show="!bpOwn(selected.typography.fontSize)" x-text="inheritNote(selected.typography.fontSize)"></p>
            <button type="button" class="mt-0.5 text-xs font-semibold text-primary-700 underline" x-show="bp !== 'desktop' && bpOwn(selected.typography.fontSize)" @click="bpClear(selected.typography.fontSize)">Use the larger screen's size</button>
        </div>
        <div>
            <label class="{{ $label }}" for="be-lh">Line height <span class="{{ $bpTag }}" x-text="bpLabels[bp]"></span></label>
            <input id="be-lh" type="number" min="0.7" max="3" step="0.05" class="{{ $num }}" :value="bpValue(selected.typography.lineHeight)"
                   @change="bpSet(selected.typography.lineHeight, $event.target.value, 0.7, 3)">
            <p class="mt-0.5 text-xs text-neutral-600" x-show="!bpOwn(selected.typography.lineHeight)" x-text="inheritNote(selected.typography.lineHeight)"></p>
            <button type="button" class="mt-0.5 text-xs font-semibold text-primary-700 underline" x-show="bp !== 'desktop' && bpOwn(selected.typography.lineHeight)" @click="bpClear(selected.typography.lineHeight)">Use the larger screen's value</button>
        </div>
        <div>
            <label class="{{ $label }}" for="be-ls">Letter spacing (em)</label>
            <input id="be-ls" type="number" min="-0.2" max="0.5" step="0.005" class="{{ $num }}" x-model.number="selected.typography.letterSpacing">
        </div>
        <div>
            <span class="{{ $label }}">Colour</span>
            <button type="button" class="{{ $swatch }} mt-1"
                    @click="selected.type === 'button'
                        ? openColor($event, 'Button text colour', () => selected.button.text, (c) => { selected.button.text = c; selected.typography.color = c })
                        : openColor($event, 'Text colour', () => selected.typography.color, (c) => selected.typography.color = c)">
                <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(selected.type === 'button' ? selected.button.text : selected.typography.color)}`"></span></span>
                <span class="font-mono text-xs" x-text="(selected.type === 'button' ? selected.button.text : selected.typography.color).a < 1 ? Math.round((selected.type === 'button' ? selected.button.text : selected.typography.color).a * 100) + '%' : 'Choose'"></span>
            </button>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-1" role="group" aria-label="Text formatting">
        <button type="button" class="{{ $toggle }}" :class="selected.typography.fontWeight >= 600 ? 'border-primary-600 bg-primary-50' : 'border-neutral-300'" :aria-pressed="selected.typography.fontWeight >= 600 ? 'true' : 'false'" @click="toggleBold()" title="Bold"><b>B</b><span class="sr-only">Bold</span></button>
        <button type="button" class="{{ $toggle }}" :class="selected.typography.italic ? 'border-primary-600 bg-primary-50' : 'border-neutral-300'" :aria-pressed="selected.typography.italic ? 'true' : 'false'"
                :disabled="!font(selected.typography.fontFamily).italic" @click="selected.typography.italic = !selected.typography.italic; typographyChanged()" title="Italic"><i>I</i><span class="sr-only">Italic</span></button>
        <button type="button" class="{{ $toggle }}" :class="selected.typography.underline ? 'border-primary-600 bg-primary-50' : 'border-neutral-300'" :aria-pressed="selected.typography.underline ? 'true' : 'false'" @click="selected.typography.underline = !selected.typography.underline" title="Underline"><u>U</u><span class="sr-only">Underline</span></button>
        <button type="button" class="{{ $toggle }}" :class="selected.typography.strike ? 'border-primary-600 bg-primary-50' : 'border-neutral-300'" :aria-pressed="selected.typography.strike ? 'true' : 'false'" @click="selected.typography.strike = !selected.typography.strike" title="Strikethrough"><s>S</s><span class="sr-only">Strikethrough</span></button>
        <span class="mx-1 h-6 border-l border-neutral-300" aria-hidden="true"></span>
        @foreach (['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'] as $value => $name)
            <button type="button" class="{{ $toggle }}" :class="selected.typography.textAlign === '{{ $value }}' ? 'border-primary-600 bg-primary-50' : 'border-neutral-300'"
                    :aria-pressed="selected.typography.textAlign === '{{ $value }}' ? 'true' : 'false'" @click="selected.typography.textAlign = '{{ $value }}'" title="Align text {{ strtolower($name) }}">{{ $name }}</button>
        @endforeach
    </div>

    <div class="mt-3">
        <label class="inline-flex min-h-9 items-center gap-2 text-sm text-neutral-900">
            <input type="checkbox" class="h-5 w-5 accent-primary-600" :checked="!!selected.typography.shadow"
                   @change="selected.typography.shadow = $event.target.checked ? { x: 0, y: 2, blur: 16, color: { r: 15, g: 23, b: 42, a: 0.55 } } : null"> Text shadow
        </label>
        <template x-if="selected.typography.shadow">
            <div class="mt-1 grid grid-cols-4 gap-2">
                <div><label class="{{ $label }}" for="be-sx">X</label><input id="be-sx" type="number" min="-50" max="50" class="{{ $num }}" x-model.number="selected.typography.shadow.x"></div>
                <div><label class="{{ $label }}" for="be-sy">Y</label><input id="be-sy" type="number" min="-50" max="50" class="{{ $num }}" x-model.number="selected.typography.shadow.y"></div>
                <div><label class="{{ $label }}" for="be-sb">Blur</label><input id="be-sb" type="number" min="0" max="100" class="{{ $num }}" x-model.number="selected.typography.shadow.blur"></div>
                <div><span class="{{ $label }}">Colour</span>
                    <button type="button" class="{{ $swatch }} mt-1 justify-center" @click="openColor($event, 'Shadow colour', () => selected.typography.shadow.color, (c) => selected.typography.shadow.color = c)">
                        <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(selected.typography.shadow.color)}`"></span></span><span class="sr-only">Shadow colour</span>
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- Button look --}}
<template x-if="selected.type === 'button'">
    <div class="{{ $section }}">
        <div class="flex items-center justify-between">
            <h3 class="{{ $h3 }}">Button</h3>
            <label class="inline-flex min-h-9 items-center gap-2 text-sm text-neutral-900"><input type="checkbox" class="h-5 w-5 accent-primary-600" x-model="hoverPreview"> Show hover look</label>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-3">
            @foreach (['bg' => 'Background', 'border' => 'Border'] as $key => $name)
                <div><span class="{{ $label }}">{{ $name }}</span>
                    <button type="button" class="{{ $swatch }} mt-1" @click="openColor($event, 'Button {{ strtolower($name) }}', () => selected.button.{{ $key }}, (c) => selected.button.{{ $key }} = c)">
                        <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(selected.button.{{ $key }})}`"></span></span>
                        <span class="font-mono text-xs" x-text="selected.button.{{ $key }}.a === 0 ? 'Transparent' : (selected.button.{{ $key }}.a < 1 ? Math.round(selected.button.{{ $key }}.a * 100) + '%' : 'Choose')"></span>
                    </button>
                </div>
            @endforeach
            <div><label class="{{ $label }}" for="be-bw">Border width (px)</label><input id="be-bw" type="number" min="0" max="12" class="{{ $num }}" x-model.number="selected.button.borderWidth"></div>
            <div><label class="{{ $label }}" for="be-br">Corner radius (px)</label><input id="be-br" type="number" min="0" max="100" class="{{ $num }}" x-model.number="selected.button.radius"></div>
            <div><label class="{{ $label }}" for="be-px">Padding left/right</label><input id="be-px" type="number" min="0" max="120" class="{{ $num }}" x-model.number="selected.button.px"></div>
            <div><label class="{{ $label }}" for="be-py">Padding top/bottom</label><input id="be-py" type="number" min="0" max="80" class="{{ $num }}" x-model.number="selected.button.py"></div>
            <div class="col-span-2"><label class="{{ $label }}" for="be-bs">Shadow</label>
                <select id="be-bs" class="{{ $input }}" x-model="selected.button.shadow"><option value="none">None</option><option value="sm">Small</option><option value="md">Medium</option><option value="lg">Large</option></select>
            </div>
        </div>
        <p class="mt-3 text-sm font-semibold text-neutral-800">When hovered</p>
        <div class="mt-1 grid grid-cols-3 gap-2">
            @foreach (['bg' => 'Background', 'text' => 'Text', 'border' => 'Border'] as $key => $name)
                <div><span class="{{ $label }}">{{ $name }}</span>
                    <button type="button" class="{{ $swatch }} mt-1 justify-center" @click="openColor($event, 'Hover {{ strtolower($name) }}', () => selected.button.hover.{{ $key }}, (c) => selected.button.hover.{{ $key }} = c)">
                        <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(selected.button.hover.{{ $key }})}`"></span></span><span class="sr-only">Hover {{ strtolower($name) }} colour</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</template>

{{-- Position and size --}}
<div class="{{ $section }}">
    <h3 class="{{ $h3 }}">Position <span class="{{ $bpTag }}" x-text="bpLabels[bp]"></span></h3>

    <template x-if="!position(selected)">
        <div class="mt-2">
            <p class="text-sm text-neutral-700">Automatic layout: stacked with the other automatic elements on the left, centred vertically (the original banner layout). Drag it on the banner, or:</p>
            <button type="button" class="{{ $btn }} mt-2" @click="ensurePosition(selected); commit()" :disabled="selected.locked">Place freely</button>
            <label class="{{ $label }} mt-3" for="be-gap">Space above (px)</label>
            <input id="be-gap" type="number" min="0" max="200" class="{{ $num }}" x-model.number="selected.gap">
        </div>
    </template>

    <template x-if="position(selected)">
        <div class="mt-2">
            <p class="text-sm text-neutral-600" x-show="!selected.position[bp]">Same as the larger screen. Changing it here sets it for this screen size only.</p>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="be-x">X (%)</label>
                    <input id="be-x" type="number" step="0.1" min="-50" max="150" class="{{ $num }}" :value="position(selected).x" :disabled="selected.locked"
                           @change="ensurePosition(selected).x = Math.max(-50, Math.min(150, +$event.target.value || 0))"></div>
                <div><label class="{{ $label }}" for="be-y">Y (%)</label>
                    <input id="be-y" type="number" step="0.1" min="-50" max="150" class="{{ $num }}" :value="position(selected).y" :disabled="selected.locked"
                           @change="ensurePosition(selected).y = Math.max(-50, Math.min(150, +$event.target.value || 0))"></div>
                <div><label class="{{ $label }}" for="be-ax">Anchor across</label>
                    <select id="be-ax" class="{{ $input }}" :value="position(selected).ax" @change="setAnchor(selected, 'ax', +$event.target.value)"><option value="0">Left edge</option><option value="50">Centre</option><option value="100">Right edge</option></select></div>
                <div><label class="{{ $label }}" for="be-ay">Anchor down</label>
                    <select id="be-ay" class="{{ $input }}" :value="position(selected).ay" @change="setAnchor(selected, 'ay', +$event.target.value)"><option value="0">Top edge</option><option value="50">Middle</option><option value="100">Bottom edge</option></select></div>
            </div>
            <p class="mt-1 text-xs text-neutral-600">X and Y are measured across the content area (the dashed box), so the layout scales with the screen.</p>
            <button type="button" class="mt-2 text-sm font-semibold text-primary-700 underline" @click="resetPosition(selected)"
                    x-text="bp === 'desktop' ? 'Back to automatic layout' : 'Use the larger screen\'s position'"></button>
        </div>
    </template>

    <p class="mt-3 text-sm font-medium text-neutral-800">Align to the banner content</p>
    <div class="mt-1 grid grid-cols-6 gap-1" role="group" aria-label="Align">
        @foreach (['left' => '⇤', 'center' => '↔', 'right' => '⇥', 'top' => '⤒', 'middle' => '↕', 'bottom' => '⤓'] as $where => $glyph)
            <button type="button" class="min-h-9 rounded-control border border-neutral-300 text-base hover:bg-neutral-50 disabled:opacity-40" @click="align('{{ $where }}')" :disabled="selected.locked" title="Align {{ $where }}">
                <span aria-hidden="true">{{ $glyph }}</span><span class="sr-only">Align {{ $where }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3">
        <div><label class="{{ $label }}" for="be-w">Width (%) <span class="{{ $bpTag }}" x-text="bpLabels[bp]"></span></label>
            <input id="be-w" type="number" min="5" max="100" step="1" class="{{ $num }}" placeholder="Auto" :value="bpValue(selected.width) ?? ''"
                   @change="$event.target.value === '' ? (selected.width[bp] = null) : bpSet(selected.width, $event.target.value, 5, 100)"></div>
        <div><label class="{{ $label }}" for="be-rot">Rotation (°)</label>
            <input id="be-rot" type="number" min="-180" max="180" step="1" class="{{ $num }}" x-model.number="selected.rotation"></div>
    </div>
</div>

{{-- Checks for this element --}}
<div class="{{ $section }}">
    <h3 class="{{ $h3 }}">Readability</h3>
    <template x-if="contrast && contrast.video">
        <p class="mt-1 text-sm text-neutral-700">Contrast cannot be checked automatically over a video. Check it by eye in Preview.</p>
    </template>
    <template x-if="contrast && !contrast.video">
        <div class="mt-1 text-sm" role="status">
            <p :class="contrast.ok ? 'text-success' : 'text-warning'">
                <span class="font-semibold" x-text="contrast.ok ? '✓ Readable' : '⚠ Low contrast'"></span>:
                <span x-text="`${contrast.ratio}:1 at its weakest point (needs ${contrast.needed}:1 for ${contrast.large ? 'large' : 'normal'} text, WCAG AA)`"></span>
            </p>
            <p class="mt-1 text-neutral-600" x-show="!contrast.ok">Try a stronger overlay behind it, a different text colour, or move it to a calmer part of the photo. This is a warning: you can still publish.</p>
            <p class="mt-1 text-neutral-600" x-show="contrast.blend">The overlay uses a blend mode, so this estimate is less exact. Check it in Preview.</p>
        </div>
    </template>
    <ul class="mt-2 flex flex-col gap-1 text-sm text-warning">
        <template x-for="(w, i) in warningsFor(selected.id)" :key="i"><li x-text="'⚠ ' + w.text"></li></template>
    </ul>
</div>

<div class="{{ $section }} flex gap-2">
    <button type="button" class="{{ $btn }} flex-1" @click="duplicate()">Duplicate</button>
    <button type="button" class="{{ $btn }} flex-1 text-danger" @click="remove()">Delete</button>
</div>
