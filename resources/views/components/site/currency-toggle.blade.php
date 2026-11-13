{{-- USD / LKR switch. Posts the choice, which is stored in a cookie, then returns to the same page. --}}
@php $current = currency()->code(); @endphp
<form action="{{ route('currency.switch') }}" method="POST" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @csrf
    <div role="group" aria-label="{{ __('site.currency.label') }}" class="inline-flex rounded-control border border-neutral-300 bg-white p-0.5">
        @foreach (\App\Support\Currency::SUPPORTED as $code)
            <button type="submit" name="currency" value="{{ $code }}"
                    aria-pressed="{{ $current === $code ? 'true' : 'false' }}"
                    title="{{ __('site.currency.switch_to', ['currency' => $code]) }}"
                    class="min-h-9 rounded-[4px] px-2.5 font-mono text-sm font-semibold transition-colors {{ $current === $code ? 'bg-primary-600 text-white' : 'text-neutral-600 hover:bg-primary-50 hover:text-primary-700' }}">
                {{ $code }}
            </button>
        @endforeach
    </div>
</form>
