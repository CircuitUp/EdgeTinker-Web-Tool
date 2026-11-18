{{-- One banner element on the live site (see slide.blade.php). $item = ['el' => element config, 'style' => inline CSS]. --}}
@php $el = $item['el']; @endphp
@if ($el['type'] === 'button')
<a href="{{ $el['href'] }}" class="bnr-el bnr-btn" style="{{ $item['style'] }}" @if ($el['newTab']) target="_blank" rel="noopener" @endif><span class="bnr-content">{{ $el['content'] }}</span>@if ($el['icon'] !== 'none')<span class="bnr-icon"><x-icon :name="$el['icon']" /></span>@endif @if ($el['newTab'])<span class="sr-only">{{ __('site.nav.new_tab') }}</span>@endif</a>
@else
<{{ $el['tag'] }} class="bnr-el bnr-text" style="{{ $item['style'] }}"><span class="bnr-content">{{ $el['content'] }}</span></{{ $el['tag'] }}>
@endif
