@props(['banner'])
{{-- The home page banner (slideshow), rendered from a BannerConfig prepared by BannerPresenter.
     Each slide is drawn by <x-banner.slide>, which the Content Studio banner editor also uses. --}}
@php $slides = $banner['slides']; @endphp
@if (count($slides) > 0)
    <section x-data="slideshow({ count: {{ count($slides) }}, interval: {{ (int) config('edgetinker.home.slide_interval_ms') }} })"
             class="bnr on-dark" style="{{ $banner['style'] }}"
             aria-roledescription="carousel" aria-label="{{ __('site.home.slideshow_label') }}"
             @mouseenter="setHovered(true)" @mouseleave="setHovered(false)"
             @focusin="setFocused(true)" @focusout="setFocused(false)"
             @keydown.arrow-left="previous()" @keydown.arrow-right="next()"
             @touchstart.passive="onTouchStart($event)" @touchend.passive="onTouchEnd($event)">

        <div class="bnr-viewport" :aria-live="playing ? 'off' : 'polite'">
            @foreach ($slides as $index => $slide)
                <div x-show="current === {{ $index }}" @if (! $loop->first) x-cloak @endif
                     x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity duration-700" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="bnr-slide" style="{{ $slide['style'] }}" role="group" aria-roledescription="slide"
                     aria-label="{{ __('site.home.slide_of', ['current' => $index + 1, 'total' => count($slides)]) }}">
                    <x-banner.slide :slide="$slide" :frame="$banner['frame']" :scrim="$banner['scrim']" :first="$loop->first" />
                </div>
            @endforeach
        </div>

        @if (count($slides) > 1)
            @php $control = 'inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/50 bg-primary-900/60 text-white transition-colors hover:bg-white/15'; @endphp
            <div class="absolute inset-x-0 bottom-5 z-20">
                <div class="container-page flex items-center justify-between gap-4">
                    <div class="flex items-center gap-1">
                        @foreach ($slides as $index => $slide)
                            <button type="button" class="group inline-flex h-11 w-8 items-center justify-center" @click="goTo({{ $index }})"
                                    :aria-current="current === {{ $index }} ? 'true' : 'false'">
                                <span class="h-2.5 rounded-full transition-all" :class="current === {{ $index }} ? 'w-6 bg-white' : 'w-2.5 bg-white/50 group-hover:bg-white/80'"></span>
                                <span class="sr-only">{{ __('site.home.go_to_slide', ['number' => $index + 1]) }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- Not shown when the visitor prefers reduced motion: the slideshow never auto-plays then. --}}
                        <button type="button" class="{{ $control }}" @click="togglePlay()" x-show="!reducedMotion">
                            <span x-show="playing"><x-icon name="pause" class="h-4 w-4" /></span>
                            <span x-show="!playing" x-cloak><x-icon name="play" class="h-4 w-4" /></span>
                            <span class="sr-only" x-text="playing ? @js(__('site.home.pause')) : @js(__('site.home.play'))">{{ __('site.home.pause') }}</span>
                        </button>
                        <button type="button" class="{{ $control }}" @click="previous()">
                            <x-icon name="chevron-left" /><span class="sr-only">{{ __('site.home.previous_slide') }}</span>
                        </button>
                        <button type="button" class="{{ $control }}" @click="next()">
                            <x-icon name="chevron-right" /><span class="sr-only">{{ __('site.home.next_slide') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </section>
@endif
