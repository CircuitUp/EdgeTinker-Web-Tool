{{-- Inspector when nothing is selected: this slide's background, then settings for the whole banner. --}}
@php
    $chip = 'h-6 w-6 shrink-0 rounded border border-neutral-300 be-checker';
    $swatch = 'inline-flex h-10 w-full items-center gap-2 rounded-control border border-neutral-500 bg-white px-2 text-sm text-neutral-900';
@endphp

<div class="{{ $section }}">
    <h3 class="{{ $h3 }}">This slide</h3>
    <label class="{{ $label }} mt-2" for="be-slide-name">Slide name (only shown here)</label>
    <input id="be-slide-name" type="text" maxlength="60" class="{{ $input }}" x-model="s.name">

    <span class="{{ $label }} mt-3" id="be-bg-type">Background</span>
    <div class="mt-1 grid grid-cols-3 overflow-hidden rounded-control border border-neutral-300" role="radiogroup" aria-labelledby="be-bg-type">
        @foreach (['image' => 'Image', 'video' => 'Video', 'color' => 'Colour'] as $type => $name)
            <button type="button" role="radio" class="min-h-9 text-sm font-semibold" :aria-checked="s.background.type === '{{ $type }}' ? 'true' : 'false'"
                    :class="s.background.type === '{{ $type }}' ? 'bg-primary-600 text-white' : 'bg-white text-neutral-800 hover:bg-neutral-50'"
                    @click="s.background.type = '{{ $type }}'">{{ $name }}</button>
        @endforeach
    </div>

    <template x-if="s.background.type === 'image'">
        <div class="mt-3">
            <div class="be-checker aspect-[16/7] overflow-hidden rounded-control border border-neutral-300">
                <template x-if="imageUrl(s.background.image)">
                    <div class="relative h-full w-full cursor-crosshair" @pointerdown="padDown($event, s.background.position)" title="Click the most important part of the image to keep it in view">
                        <img :src="imageUrl(s.background.image)" alt="" class="h-full w-full object-cover" draggable="false">
                        <span class="be-pad-dot" :style="`left:${s.background.position.x}%;top:${s.background.position.y}%`"></span>
                    </div>
                </template>
            </div>
            <p class="mt-1 truncate text-sm text-neutral-700" x-text="backgroundName()"></p>
            <div class="mt-2 flex gap-2">
                <button type="button" class="{{ $btn }} flex-1" @click="openLibrary('image')">Choose or upload</button>
            </div>
            <p class="mt-2 text-sm text-neutral-600">About 1920 × 900 px works best. Click the preview to choose the point that stays in view when the banner is cropped (focal point).</p>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="be-fx">Focal X (%)</label><input id="be-fx" type="number" min="0" max="100" class="{{ $num }}" x-model.number="s.background.position.x"></div>
                <div><label class="{{ $label }}" for="be-fy">Focal Y (%)</label><input id="be-fy" type="number" min="0" max="100" class="{{ $num }}" x-model.number="s.background.position.y"></div>
                <div class="col-span-2"><label class="{{ $label }}" for="be-fit">Fit</label>
                    <select id="be-fit" class="{{ $input }}" x-model="s.background.size"><option value="cover">Fill the banner (may crop)</option><option value="contain">Show the whole image</option></select></div>
            </div>
            <label class="{{ $label }} mt-3" for="be-alt">Image description for screen readers</label>
            <input id="be-alt" type="text" maxlength="255" class="{{ $input }}" x-model="s.background.alt" placeholder="Leave empty if purely decorative">
        </div>
    </template>

    <template x-if="s.background.type === 'video'">
        <div class="mt-3">
            <p class="truncate text-sm text-neutral-700" x-text="backgroundName()"></p>
            <button type="button" class="{{ $btn }} mt-2 w-full" @click="openLibrary('video')">Choose or upload a video</button>
            <p class="mt-2 text-sm text-neutral-600">A short MP4 or WebM loop (up to 30 MB). It plays muted, and stays still for visitors who ask for less motion. The background colour below shows while it loads.</p>
            <label class="{{ $label }} mt-3" for="be-alt-v">Video description for screen readers</label>
            <input id="be-alt-v" type="text" maxlength="255" class="{{ $input }}" x-model="s.background.alt" placeholder="Leave empty if purely decorative">
        </div>
    </template>

    <div class="mt-3">
        <span class="{{ $label }}" x-text="s.background.type === 'color' ? 'Background colour' : 'Colour behind the image or video'"></span>
        <button type="button" class="{{ $swatch }} mt-1" @click="openColor($event, 'Background colour', () => s.background.color, (c) => s.background.color = c)">
            <span class="{{ $chip }}"><span class="block h-full w-full rounded" :style="`background:${swatch(s.background.color)}`"></span></span><span class="text-xs">Choose</span>
        </button>
    </div>
</div>

<div class="{{ $section }}">
    <h3 class="{{ $h3 }}">Whole banner</h3>
    <p class="mt-1 text-sm text-neutral-600">These apply to every slide.</p>
    <p class="mt-3 text-sm font-medium text-neutral-800">Height (px)</p>
    <div class="mt-1 grid grid-cols-3 gap-2">
        <template x-for="b in breakpoints" :key="b">
            <div><label class="{{ $label }}" :for="`be-h-${b}`" x-text="bpLabels[b]"></label>
                <input :id="`be-h-${b}`" type="number" min="200" max="1200" step="8" class="{{ $num }}" :value="config.height[b]"
                       @change="config.height[b] = Math.max(b === 'desktop' ? 240 : 200, Math.min(1200, Math.round(+$event.target.value || config.height[b])))"></div>
        </template>
    </div>

    <label class="{{ $label }} mt-3" for="be-frame">Position elements within</label>
    <select id="be-frame" class="{{ $input }}" x-model="config.frame">
        <option value="container">The page content width (lines up with the rest of the page)</option>
        <option value="full">The full banner width</option>
    </select>

    <label class="mt-3 inline-flex min-h-10 items-center gap-2 text-sm text-neutral-900">
        <input type="checkbox" class="h-5 w-5 accent-primary-600" x-model="config.scrim"> Shade behind the slide dots and arrows
    </label>
</div>
