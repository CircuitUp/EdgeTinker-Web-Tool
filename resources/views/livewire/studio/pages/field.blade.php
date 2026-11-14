{{--
    One schema field of a page form.
    $field  schema entry (key, label, type, hint, required, max)
    $model  Livewire property path, e.g. "form.content.heading" or "form.content.slides.0.heading"
    $value  current value
    $upload (image fields) path under `uploads`, e.g. "slides.0.image"; $media = Media keyed by id
--}}
@php
    $id = 'p-'.str_replace(['.', '_'], '-', $model);
    $control = 'block w-full rounded-control border bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500 '
        .($errors->has($model) ? 'border-danger' : 'border-neutral-300 hover:border-neutral-400');
    $required = $field['required'] ?? false;
@endphp

@switch($field['type'])
    @case('textarea')
    @case('lines')
        <x-form.field :label="$field['label']" :for="$id" :hint="$field['hint'] ?? null" :required="$required" :error="$errors->first($model)">
            <textarea id="{{ $id }}" rows="{{ $field['type'] === 'lines' ? 4 : 3 }}" wire:model="{{ $model }}" maxlength="{{ $field['type'] === 'lines' ? 2000 : ($field['max'] ?? 1000) }}"
                      aria-describedby="{{ $id }}-hint {{ $id }}-error" @error($model) aria-invalid="true" @enderror class="{{ $control }}"></textarea>
        </x-form.field>
        @break

    @case('icon')
        <x-form.field :label="$field['label']" :for="$id" :required="$required" :error="$errors->first($model)">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-card bg-primary-100 text-primary-700" aria-hidden="true">
                    <x-icon :name="in_array($value, \App\Support\PageContent::ICONS, true) ? $value : 'cpu'" />
                </span>
                <select id="{{ $id }}" wire:model.live="{{ $model }}" class="{{ $control }} min-h-11">
                    @foreach (\App\Support\PageContent::ICONS as $icon)
                        <option value="{{ $icon }}">{{ Str::headline($icon) }}</option>
                    @endforeach
                </select>
            </div>
        </x-form.field>
        @break

    @case('image')
        @php
            $url = ($value['media_id'] ?? null) ? (isset($media[$value['media_id']]) ? $media[$value['media_id']]->variantUrl(800) : null) : media_url($value['path'] ?? null);
        @endphp
        <div>
            <p class="text-sm font-medium text-neutral-900">{{ $field['label'] }}</p>
            @isset($field['hint'])<p class="text-sm text-neutral-600">{{ $field['hint'] }}</p>@endisset
            @if ($url)
                <div class="mt-2 flex flex-wrap items-start gap-3">
                    <img src="{{ $url }}" alt="Current image" class="h-24 w-44 rounded-control border border-neutral-200 object-cover">
                    <x-button variant="ghost-danger" size="sm" wire:click="removeImage('{{ $rowKey }}', {{ $rowIndex }}, '{{ $field['key'] }}')">Remove image</x-button>
                </div>
            @endif
            <label for="{{ $id }}" class="sr-only">Upload: {{ $field['label'] }}</label>
            <input id="{{ $id }}" type="file" wire:model="uploads.{{ $upload }}" accept=".jpg,.jpeg,.png,.webp,.gif"
                   class="mt-2 block w-full text-base text-neutral-700 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-primary-600 file:bg-white file:px-4 file:font-semibold file:text-primary-700 hover:file:bg-primary-50">
            <p wire:loading wire:target="uploads.{{ $upload }}" class="mt-1 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
            @foreach (["uploads.$upload", "$model.media_id", "$model.path"] as $errorKey)
                @error($errorKey)<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
            @endforeach
        </div>
        @break

    @default {{-- text, link --}}
        <x-form.field :label="$field['label']" :for="$id" :hint="$field['hint'] ?? null" :required="$required" :error="$errors->first($model)">
            <input id="{{ $id }}" type="text" wire:model="{{ $model }}" maxlength="{{ $field['max'] ?? 255 }}"
                   aria-describedby="{{ $id }}-hint {{ $id }}-error" @error($model) aria-invalid="true" @enderror
                   class="{{ $control }} min-h-11 {{ $field['type'] === 'link' ? 'font-mono' : '' }}">
        </x-form.field>
@endswitch
