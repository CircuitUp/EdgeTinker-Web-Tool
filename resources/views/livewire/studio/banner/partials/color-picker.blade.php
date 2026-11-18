{{-- One shared colour picker; openColor() points it at the colour being edited. Changes apply live. --}}
<div x-show="picker.open" x-cloak x-ref="pickerPanel" tabindex="-1" role="dialog" :aria-label="picker.label" data-no-nudge
     class="fixed z-[70] w-72 rounded-card border border-neutral-200 bg-white p-3 shadow-overlay outline-none"
     :style="`left:${picker.x}px;top:${picker.y}px`" @click.outside="closeColor()" @keydown.escape.stop="closeColor()">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-neutral-900" x-text="picker.label"></p>
        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-control hover:bg-neutral-100" @click="closeColor()"><x-icon name="x" class="h-4 w-4" /><span class="sr-only">Close</span></button>
    </div>

    {{-- Saturation (across) and brightness (down) for the chosen hue --}}
    <div class="be-sv mt-2" :style="`background-color:hsl(${picker.h},100%,50%)`" @pointerdown="svDown($event)" @keydown="svKey($event)"
         tabindex="0" role="slider" aria-label="Saturation and brightness. Arrow keys adjust." :aria-valuetext="picker.text">
        <span class="be-sv-dot" :style="`left:${picker.s * 100}%;top:${(1 - picker.v) * 100}%`"></span>
    </div>

    <label class="mt-3 block text-xs font-medium text-neutral-700" for="be-hue">Hue</label>
    <input id="be-hue" type="range" min="0" max="360" step="1" class="be-hue w-full" x-model.number="picker.h" @input="applyPicker()">

    <label class="mt-2 block text-xs font-medium text-neutral-700" for="be-alpha">Opacity <span x-text="Math.round(picker.a * 100) + '%'"></span><span x-show="picker.a === 0"> (transparent)</span></label>
    <div class="be-checker rounded-full">
        <input id="be-alpha" type="range" min="0" max="100" step="1" class="be-alpha w-full" :value="Math.round(picker.a * 100)"
               :style="`--from:rgba(${pickerColor().r},${pickerColor().g},${pickerColor().b},0);--to:rgb(${pickerColor().r},${pickerColor().g},${pickerColor().b})`"
               @input="picker.a = $event.target.value / 100; applyPicker()">
    </div>

    <div class="mt-3 flex gap-2">
        <label class="sr-only" for="be-format">Colour format</label>
        <select id="be-format" class="min-h-9 rounded-control border border-neutral-500 bg-white px-1 text-sm" x-model="picker.format" @change="syncPickerText()">
            <option value="hex">HEX</option><option value="rgb">RGB</option><option value="hsl">HSL</option>
        </select>
        <label class="sr-only" for="be-color-text">Colour value</label>
        <input id="be-color-text" type="text" spellcheck="false" class="min-h-9 min-w-0 flex-1 rounded-control border border-neutral-500 px-2 font-mono text-sm"
               x-model="picker.text" @change="pickerTyped()" @keydown.enter.prevent="pickerTyped()">
        <button type="button" x-show="canEyedrop" class="inline-flex h-9 w-9 items-center justify-center rounded-control border border-neutral-300 hover:bg-neutral-50" @click="eyedrop()" title="Pick a colour from the screen">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m2 22 1-1h3l9-9"/><path d="M3 21v-3l9-9"/><path d="m15 6 3.4-3.4a2.1 2.1 0 1 1 3 3L18 9l.4.4a2.1 2.1 0 1 1-3 3l-3.8-3.8a2.1 2.1 0 1 1 3-3l.4.4Z"/></svg>
            <span class="sr-only">Eyedropper</span>
        </button>
    </div>

    <template x-if="recent.length">
        <div class="mt-3">
            <p class="text-xs font-medium text-neutral-700">Recently used</p>
            <div class="mt-1 flex flex-wrap gap-1">
                <template x-for="hex in recent" :key="hex">
                    <button type="button" class="be-checker h-6 w-6 rounded border border-neutral-300" @click="setColorFrom(hex)" :title="hex">
                        <span class="block h-full w-full rounded" :style="`background:${hex}`"></span><span class="sr-only" x-text="hex"></span>
                    </button>
                </template>
            </div>
        </div>
    </template>

    <p class="mt-3 text-xs font-medium text-neutral-700">EdgeTinker colours</p>
    <div class="mt-1 flex flex-wrap gap-1">
        <template x-for="[name, hex] in brand" :key="hex">
            <button type="button" class="h-6 w-6 rounded border border-neutral-300" :style="`background:${hex}`" @click="setColorFrom(hex)" :title="`${name} ${hex}`">
                <span class="sr-only" x-text="name"></span>
            </button>
        </template>
        <button type="button" class="be-checker h-6 w-6 rounded border border-neutral-300" @click="picker.a = 0; applyPicker()" title="Transparent"><span class="sr-only">Transparent</span></button>
    </div>
</div>
