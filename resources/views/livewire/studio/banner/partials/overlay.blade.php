{{-- Inspector: the overlay of the current slide at the current screen size. --}}
@php
    $chip = 'h-6 w-6 shrink-0 rounded border border-neutral-300 be-checker';
    $swatch = 'inline-flex h-10 w-full items-center gap-2 rounded-control border border-neutral-500 bg-white px-2 text-sm text-neutral-900';
@endphp

<div class="{{ $section }}">
    <p class="text-sm text-neutral-700">The colour layer between the background and the text. It keeps text readable on busy photos.</p>
    <p class="mt-2 text-sm font-medium text-neutral-800">
        Editing the <span class="font-semibold" x-text="bpLabels[bp].toLowerCase()"></span> overlay
        <span x-show="bp === 'desktop'">(tablet and mobile follow it unless they have their own)</span>
    </p>

    <template x-if="!overlayOwn">
        <div class="mt-3 rounded-card border border-neutral-200 bg-neutral-50 p-3">
            <p class="text-sm text-neutral-800" x-text="`The ${bpLabels[bp].toLowerCase()} banner uses the ${bp === 'mobile' && s.overlay.tablet ? 'tablet' : 'desktop'} overlay.`"></p>
            <div class="mt-2 h-8 rounded-control border border-neutral-300 be-checker"><div class="h-full rounded-control" :style="`background:${overlayCss(overlay)};opacity:${overlay.opacity}`"></div></div>
            <button type="button" class="{{ $btn }} mt-3" @click="customiseOverlay()" x-text="`Give ${bpLabels[bp].toLowerCase()} its own overlay`"></button>
        </div>
    </template>
</div>

<template x-if="overlayOwn">
    <div>
        <div class="{{ $section }}">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-neutral-900" id="be-ov-on">Overlay</span>
                <button type="button" role="switch" :aria-checked="overlayOwn.enabled ? 'true' : 'false'" aria-labelledby="be-ov-on"
                        class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors" :class="overlayOwn.enabled ? 'bg-primary-600' : 'bg-neutral-400'"
                        @click="overlayOwn.enabled = !overlayOwn.enabled">
                    <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="overlayOwn.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                    <span class="sr-only" x-text="overlayOwn.enabled ? 'On' : 'Off'"></span>
                </button>
            </div>
            <p class="mt-1 text-sm text-neutral-600" x-show="!overlayOwn.enabled">Off: the background shows with no colour layer at all.</p>
            <button type="button" class="mt-2 text-sm font-semibold text-primary-700 underline" x-show="bp !== 'desktop'" @click="inheritOverlay()"
                    x-text="`Use the ${bp === 'mobile' ? 'tablet / desktop' : 'desktop'} overlay instead`"></button>
        </div>

        <template x-if="overlayOwn.enabled">
            <div>
                <div class="{{ $section }}">
                    <span class="{{ $label }}" id="be-ov-mode">Type</span>
                    <div class="mt-1 grid grid-cols-3 overflow-hidden rounded-control border border-neutral-300" role="radiogroup" aria-labelledby="be-ov-mode">
                        @foreach (['solid' => 'Solid', 'linear-gradient' => 'Linear', 'radial-gradient' => 'Radial'] as $mode => $name)
                            <button type="button" role="radio" class="min-h-9 text-sm font-semibold" :aria-checked="overlayOwn.mode === '{{ $mode }}' ? 'true' : 'false'"
                                    :class="overlayOwn.mode === '{{ $mode }}' ? 'bg-primary-600 text-white' : 'bg-white text-neutral-800 hover:bg-neutral-50'"
                                    @click="overlayOwn.mode = '{{ $mode }}'; stopIndex = 0">{{ $name }}</button>
                        @endforeach
                    </div>

                    {{-- Solid --}}
                    <template x-if="overlayOwn.mode === 'solid'">
                        <div class="mt-3">
                            <span class="{{ $label }}">Colour</span>
                            <button type="button" class="{{ $swatch }} mt-1" @click="openColor($event, 'Overlay colour', () => overlayOwn.solid.color, (c) => overlayOwn.solid.color = c)">
                                <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(overlayOwn.solid.color)}`"></span></span>
                                <span class="font-mono text-xs" x-text="`${Math.round(overlayOwn.solid.color.a * 100)}% opaque`"></span>
                            </button>
                        </div>
                    </template>

                    {{-- Gradients --}}
                    <template x-if="overlayOwn.mode !== 'solid'">
                        <div class="mt-3">
                            <span class="{{ $label }}">Colour stops</span>
                            <p class="text-xs text-neutral-600">Drag a stop to move it, click the bar to add one (up to 4). A stop can be fully transparent.</p>
                            <div class="relative mt-6 h-8 cursor-copy rounded-control border border-neutral-400 be-checker" @click.self="addStop($event)">
                                <div class="pointer-events-none absolute inset-0 rounded-control" :style="`background:${gradientBar(overlayOwn)}`"></div>
                                <template x-for="(stop, i) in overlayOwn.gradient.stops" :key="i">
                                    <button type="button" class="be-stop" :class="stopIndex === i && 'is-active'" :style="`left:${stop.position}%`"
                                            @pointerdown="stopDown($event, i)" @keydown="stopKey($event, i)" @click.stop="stopIndex = i"
                                            :aria-label="`Stop ${i + 1} at ${stop.position}%. Arrow keys move it.`">
                                        <span class="block h-full w-full rounded-sm be-checker"><span class="block h-full w-full rounded-sm" :style="`background:${swatch(stop.color)}`"></span></span>
                                    </button>
                                </template>
                            </div>

                            <template x-if="stop">
                                <div class="mt-3 grid grid-cols-2 gap-3 rounded-card border border-neutral-200 bg-neutral-50 p-3">
                                    <div class="col-span-2 flex items-center justify-between">
                                        <span class="text-sm font-semibold text-neutral-900" x-text="`Stop ${stopIndex + 1}`"></span>
                                        <button type="button" class="text-sm font-semibold text-danger underline disabled:opacity-40" @click="removeStop()" :disabled="overlayOwn.gradient.stops.length <= 2">Remove stop</button>
                                    </div>
                                    <div><span class="{{ $label }}">Colour</span>
                                        <button type="button" class="{{ $swatch }} mt-1" @click="openColor($event, `Stop ${stopIndex + 1} colour`, () => stop.color, (c) => stop.color = c)">
                                            <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(stop.color)}`"></span></span><span class="text-xs">Choose</span>
                                        </button>
                                    </div>
                                    <div><label class="{{ $label }}" for="be-stop-pos">Position (%)</label>
                                        <input id="be-stop-pos" type="number" min="0" max="100" step="1" class="{{ $num }}" x-model.number="stop.position"></div>
                                    <div class="col-span-2"><label class="{{ $label }}" for="be-stop-alpha">Opacity <span x-text="Math.round(stop.color.a * 100) + '%'"></span> <span class="text-neutral-600" x-show="stop.color.a === 0">(transparent)</span></label>
                                        <input id="be-stop-alpha" type="range" min="0" max="100" class="w-full" :value="Math.round(stop.color.a * 100)" @input="stop.color.a = $event.target.value / 100"></div>
                                </div>
                            </template>
                            <button type="button" class="{{ $btn }} mt-3" @click="swapStops()">⇄ Swap colours</button>
                        </div>
                    </template>

                    {{-- Linear: angle --}}
                    <template x-if="overlayOwn.mode === 'linear-gradient'">
                        <div class="mt-4 flex items-center gap-4">
                            <div class="be-dial" @pointerdown="angleDown($event)" role="slider" tabindex="0" aria-label="Gradient angle" :aria-valuenow="overlayOwn.gradient.angle" aria-valuemin="0" aria-valuemax="359"
                                 @keydown.arrow-right.prevent="overlayOwn.gradient.angle = (overlayOwn.gradient.angle + ($event.shiftKey ? 15 : 1)) % 360"
                                 @keydown.arrow-left.prevent="overlayOwn.gradient.angle = (overlayOwn.gradient.angle + 360 - ($event.shiftKey ? 15 : 1)) % 360">
                                <span class="be-dial-hand" :style="`transform:rotate(${overlayOwn.gradient.angle}deg)`"></span>
                            </div>
                            <div class="flex-1"><label class="{{ $label }}" for="be-angle">Angle (°)</label>
                                <input id="be-angle" type="number" min="0" max="360" class="{{ $num }}" x-model.number="overlayOwn.gradient.angle">
                                <p class="mt-1 text-xs text-neutral-600">0° bottom to top, 90° left to right.</p></div>
                        </div>
                    </template>

                    {{-- Radial: shape and centre --}}
                    <template x-if="overlayOwn.mode === 'radial-gradient'">
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div><label class="{{ $label }}" for="be-shape">Shape</label>
                                <select id="be-shape" class="{{ $input }}" x-model="overlayOwn.gradient.shape"><option value="ellipse">Ellipse</option><option value="circle">Circle</option></select></div>
                            <div>
                                <span class="{{ $label }}">Centre</span>
                                <div class="be-pad mt-1" @pointerdown="padDown($event, overlayOwn.gradient.center)" title="Click or drag to set the centre">
                                    <span class="be-pad-dot" :style="`left:${overlayOwn.gradient.center.x}%;top:${overlayOwn.gradient.center.y}%`"></span>
                                </div>
                            </div>
                            <div><label class="{{ $label }}" for="be-cx">Centre X (%)</label><input id="be-cx" type="number" min="0" max="100" class="{{ $num }}" x-model.number="overlayOwn.gradient.center.x"></div>
                            <div><label class="{{ $label }}" for="be-cy">Centre Y (%)</label><input id="be-cy" type="number" min="0" max="100" class="{{ $num }}" x-model.number="overlayOwn.gradient.center.y"></div>
                        </div>
                    </template>
                </div>

                <div class="{{ $section }} grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="{{ $label }}" for="be-ov-opacity">Overall opacity <span x-text="Math.round(overlayOwn.opacity * 100) + '%'"></span></label>
                        <input id="be-ov-opacity" type="range" min="0" max="100" class="w-full" :value="Math.round(overlayOwn.opacity * 100)" @input="overlayOwn.opacity = $event.target.value / 100"></div>
                    <div class="col-span-2"><label class="{{ $label }}" for="be-blend">Blend mode</label>
                        <select id="be-blend" class="{{ $input }}" x-model="overlayOwn.blendMode">
                            <option value="normal">Normal</option><option value="multiply">Multiply (darkens)</option><option value="screen">Screen (lightens)</option>
                            <option value="overlay">Overlay (adds contrast)</option><option value="soft-light">Soft light</option>
                        </select></div>
                </div>
            </div>
        </template>
    </div>
</template>
