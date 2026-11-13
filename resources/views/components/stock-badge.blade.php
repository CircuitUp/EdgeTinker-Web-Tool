@props(['status']) {{-- App\Enums\StockStatus --}}
<x-badge :variant="$status->variant()" {{ $attributes }}>{{ $status->label() }}</x-badge>
