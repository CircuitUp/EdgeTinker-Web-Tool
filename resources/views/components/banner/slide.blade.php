@props(['slide' => null, 'frame' => 'container', 'scrim' => true, 'first' => false, 'editor' => false])
{{--
    One banner slide: background, overlay, controls shade and the text/button elements.

    Shared by the live home page and the Content Studio banner editor, so both draw the same
    markup with the same stylesheet (resources/css/banner.css). The live version is rendered
    on the server from BannerPresenter data. The editor version ($editor = true) renders the
    same structure from the editor's Alpine state, so the canvas updates while dragging.
    When changing one branch, change the other to match.
--}}
@if (! $editor)
    @if ($slide['type'] === 'video' && $slide['video'])
        {{-- Decorative: muted, looping, paused for visitors who prefer reduced motion (see slideshow.js). --}}
        <video class="bnr-bg" src="{{ $slide['video'] }}" autoplay muted loop playsinline preload="metadata"
               @if ($slide['alt'] !== '') role="img" aria-label="{{ $slide['alt'] }}" @else aria-hidden="true" @endif></video>
    @elseif ($slide['type'] === 'image' && $slide['image'])
        <img src="{{ $slide['image']['src'] }}" alt="{{ $slide['alt'] }}" width="{{ $slide['image']['width'] }}" height="{{ $slide['image']['height'] }}"
             @if ($slide['image']['srcset']) srcset="{{ $slide['image']['srcset'] }}" sizes="100vw" @endif
             class="bnr-bg" @if ($first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
    @endif
    <div class="bnr-overlay" aria-hidden="true"></div>
    @if ($scrim)
        <div class="bnr-scrim" aria-hidden="true"></div>
    @endif

    <div @class(['bnr-frame', 'bnr-frame--container' => $frame === 'container'])>
        <div class="bnr-area">
            {{-- Elements are listed in layer order, which is also the reading order. --}}
            {{-- Joined without whitespace, exactly like the editor's x-for: spaces between buttons on one line would otherwise differ. --}}
            <div class="bnr-stack">{!! collect($slide['elements'])->map(fn (array $item) => trim(view('components.banner.element', ['item' => $item])->render()))->implode('') !!}</div>
        </div>
    </div>
@else
    {{-- Editor canvas: the same structure, bound to the bannerEditor Alpine component (`s` = the slide being edited). --}}
    <template x-if="s.background.type === 'video' && mediaUrl(s.background.video.media_id)">
        <video class="bnr-bg" :src="mediaUrl(s.background.video.media_id)" autoplay muted loop playsinline aria-hidden="true"></video>
    </template>
    <template x-if="s.background.type === 'image' && imageUrl(s.background.image)">
        <img class="bnr-bg" :src="imageUrl(s.background.image)" alt="" draggable="false">
    </template>
    <div class="bnr-overlay" aria-hidden="true"></div>
    <div class="bnr-scrim" x-show="config.scrim" aria-hidden="true"></div>

    <div class="bnr-frame" :class="config.frame === 'container' && 'bnr-frame--container'">
        <div class="bnr-area">
            <div class="bnr-stack">
                <template x-for="(el, layer) in s.elements" :key="el.id">
                    <div :class="[el.type === 'button' ? 'bnr-el bnr-btn' : 'bnr-el bnr-text', { 'is-hover': el.type === 'button' && hoverPreview && selectedId === el.id, 'is-selected': selectedId === el.id, 'is-locked': el.locked }]"
                         :style="elementStyle(el, layer)" :data-el="el.id" x-show="el.visible"
                         @pointerdown="pointerDown($event, el)" @dblclick="startInlineEdit($event, el)"><span class="bnr-content" x-text="el.content"></span><template x-if="el.type === 'button' && el.icon !== 'none'"><span class="bnr-icon" x-html="icons[el.icon]"></span></template></div>
                </template>
            </div>
        </div>
    </div>
@endif
