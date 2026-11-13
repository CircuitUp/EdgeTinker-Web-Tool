@props(['id', 'rows' => 4, 'invalid' => false])
<textarea id="{{ $id }}" rows="{{ $rows }}"
          aria-describedby="{{ $id }}-hint {{ $id }}-error"
          @if ($invalid) aria-invalid="true" @endif
          {{ $attributes->merge(['name' => $id])->class([
              'block w-full rounded-control border bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500',
              'disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-500',
              'border-danger' => $invalid,
              'border-neutral-300 hover:border-neutral-400' => ! $invalid,
          ]) }}>{{ $slot }}</textarea>
