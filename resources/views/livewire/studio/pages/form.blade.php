@php $iconButton = 'inline-flex h-10 w-10 items-center justify-center rounded-control text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 disabled:opacity-40'; @endphp
<div>
    <x-studio.page-header :title="'Edit the '.$title.' page'"
        :intro="'The live page does not change until '.($isAdmin ? 'you publish.' : 'the Content Admin approves your changes.')"
        :back="route('studio.pages.index')" back-label="All pages" />

    <div class="flex max-w-4xl flex-col gap-6 pb-28">
        @if ($revision?->needs_rebase)
            <x-studio.alert variant="warning" title="This page changed while you were editing">{{ $revision->rebase_note }}</x-studio.alert>
        @endif
        @if ($revision?->status === \App\Enums\RevisionStatus::Rejected)
            <x-studio.alert variant="danger" title="The Content Admin asked for changes">{{ $revision->comment }}</x-studio.alert>
        @endif

        @foreach ($groups as $group => $fields)
            <x-studio.card :title="$group">
                {{-- minmax(0, 1fr): lets long, non-wrapping text truncate instead of widening the page. --}}
                <div class="grid grid-cols-[minmax(0,1fr)] gap-5">
                    @foreach ($fields as $field)
                        @php $key = $field['key']; $value = $form['content'][$key] ?? null; @endphp

                        @if ($field['type'] === 'repeater')
                            <div>
                                @isset($field['hint'])<p class="mb-3 text-base text-neutral-600">{{ $field['hint'] }}</p>@endisset
                                @error("form.content.$key")<p class="mb-3 text-sm font-medium text-danger">{{ $message }}</p>@enderror

                                <ol class="flex flex-col gap-4">
                                    @foreach ($value as $i => $row)
                                        <li wire:key="{{ $key }}-{{ $i }}" class="rounded-card border border-neutral-200 bg-neutral-50 p-4">
                                            <div class="mb-3 flex items-center justify-between gap-2">
                                                <p class="text-base font-semibold text-neutral-900">{{ $field['row_label'] ?? 'Row' }} {{ $i + 1 }}</p>
                                                <div class="flex items-center">
                                                    <button type="button" class="{{ $iconButton }}" wire:click="moveRow('{{ $key }}', {{ $i }}, {{ $i - 1 }})" @disabled($loop->first)><x-icon name="chevron-left" class="rotate-90" /><span class="sr-only">Move {{ $field['row_label'] ?? 'row' }} {{ $i + 1 }} up</span></button>
                                                    <button type="button" class="{{ $iconButton }}" wire:click="moveRow('{{ $key }}', {{ $i }}, {{ $i + 1 }})" @disabled($loop->last)><x-icon name="chevron-right" class="rotate-90" /><span class="sr-only">Move {{ $field['row_label'] ?? 'row' }} {{ $i + 1 }} down</span></button>
                                                    <button type="button" class="{{ $iconButton }} hover:text-danger" wire:click="removeRow('{{ $key }}', {{ $i }})"
                                                            wire:confirm="Remove {{ strtolower($field['row_label'] ?? 'row') }} {{ $i + 1 }}?"><x-icon name="x" /><span class="sr-only">Remove {{ $field['row_label'] ?? 'row' }} {{ $i + 1 }}</span></button>
                                                </div>
                                            </div>
                                            <div class="grid gap-4">
                                                @foreach ($field['fields'] as $sub)
                                                    @include('livewire.studio.pages.field', [
                                                        'field' => $sub,
                                                        'model' => "form.content.$key.$i.{$sub['key']}",
                                                        'value' => $row[$sub['key']] ?? null,
                                                        'upload' => "$key.$i.{$sub['key']}",
                                                        'rowKey' => $key, 'rowIndex' => $i,
                                                    ])
                                                @endforeach
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                                @if (count($value) < ($field['max_rows'] ?? 20))
                                    <x-button variant="secondary" size="sm" class="mt-3" wire:click="addRow('{{ $key }}')">Add a {{ strtolower($field['row_label'] ?? 'row') }}</x-button>
                                @endif
                            </div>

                        @elseif ($field['type'] === 'products')
                            <div>
                                <p class="text-sm font-medium text-neutral-900">{{ $field['label'] }}</p>
                                @isset($field['hint'])<p class="text-sm text-neutral-600">{{ $field['hint'] }}</p>@endisset
                                @if (empty($value))
                                    <p class="mt-2 text-base text-neutral-600">No products are featured.</p>
                                @else
                                    <ol class="mt-2 flex flex-col divide-y divide-neutral-200 rounded-card border border-neutral-200 bg-white">
                                        @foreach ($value as $i => $productId)
                                            @php $product = $products->firstWhere('id', $productId); @endphp
                                            <li wire:key="featured-{{ $productId }}" class="flex items-center justify-between gap-2 px-3 py-1.5">
                                                <span class="min-w-0 truncate text-base text-neutral-900">{{ $i + 1 }}. {{ $product?->name ?? 'Deleted product' }} <span class="font-mono text-sm text-neutral-600">{{ $product?->sku }}</span></span>
                                                <span class="flex shrink-0 items-center">
                                                    <button type="button" class="{{ $iconButton }}" wire:click="moveRow('{{ $key }}', {{ $i }}, {{ $i - 1 }})" @disabled($loop->first)><x-icon name="chevron-left" class="rotate-90" /><span class="sr-only">Move {{ $product?->name }} up</span></button>
                                                    <button type="button" class="{{ $iconButton }}" wire:click="moveRow('{{ $key }}', {{ $i }}, {{ $i + 1 }})" @disabled($loop->last)><x-icon name="chevron-right" class="rotate-90" /><span class="sr-only">Move {{ $product?->name }} down</span></button>
                                                    <button type="button" class="{{ $iconButton }} hover:text-danger" wire:click="removeProduct('{{ $key }}', {{ $i }})"><x-icon name="x" /><span class="sr-only">Stop featuring {{ $product?->name }}</span></button>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                                @if (count($value ?? []) < ($field['max_rows'] ?? 12))
                                    <label for="product-to-add" class="mt-3 block text-sm font-medium text-neutral-900">Add a product</label>
                                    <select id="product-to-add" wire:model.live="productToAdd" class="mt-1 block min-h-11 w-full max-w-md rounded-control border border-neutral-300 bg-white px-3 text-base hover:border-neutral-400">
                                        <option value="">Choose a product…</option>
                                        @foreach ($products->whereNotIn('id', $value ?? []) as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error("form.content.$key")<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>

                        @else
                            @include('livewire.studio.pages.field', ['field' => $field, 'model' => "form.content.$key", 'value' => $value, 'upload' => null, 'rowKey' => null, 'rowIndex' => null])
                        @endif
                    @endforeach
                </div>
            </x-studio.card>
        @endforeach

        <x-studio.card title="Search engines" intro="Optional. Leave empty to use the page’s own heading and introduction.">
            <div class="grid gap-5">
                <x-studio.input label="Title for search engines" model="form.seo_title" />
                <x-form.field label="Description for search engines" for="p-seo-description" :error="$errors->first('form.seo_description')">
                    <textarea id="p-seo-description" rows="2" maxlength="500" wire:model="form.seo_description"
                              class="block w-full rounded-control border border-neutral-300 bg-white px-3 py-2 text-base hover:border-neutral-400"></textarea>
                </x-form.field>
            </div>
        </x-studio.card>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-neutral-200 bg-white lg:left-64">
        <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-end gap-2 px-4 py-3 sm:px-6 lg:px-8">
            <p wire:loading wire:target="saveDraft, submit" class="mr-auto text-sm font-semibold text-primary-700" role="status">Saving…</p>
            @if ($revision)
                <x-button :href="route('studio.revisions.preview', $revision)" variant="ghost" target="_blank" rel="noopener">
                    Preview last saved draft <x-icon name="external" class="h-4 w-4" /><span class="sr-only">(opens in a new tab)</span>
                </x-button>
            @endif
            <x-button variant="secondary" wire:click="saveDraft" wire:loading.attr="disabled">Save draft</x-button>
            <x-button wire:click="submit" wire:loading.attr="disabled">{{ $isAdmin ? 'Publish now' : 'Submit for approval' }}</x-button>
        </div>
    </div>
</div>
