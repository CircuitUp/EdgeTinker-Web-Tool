@php
    $control = 'block w-full rounded-control border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500 hover:border-neutral-400';
    $tool = 'inline-flex min-h-9 items-center rounded-control border border-neutral-300 bg-white px-2.5 text-sm font-medium text-neutral-900 hover:border-primary-600 hover:text-primary-700';
    // Toolbar buttons: label => [text before the selection, text after it, placeholder used when nothing is selected]
    // Block-level items start with a blank line, otherwise Markdown would join them onto the paragraph or quote above.
    $snippets = [
        'Heading' => ["\n\n## ", "\n\n", 'Heading'],
        'Bold' => ['**', '**', 'bold text'],
        'Link' => ['[', '](https://)', 'link text'],
        'List' => ["\n\n- ", "\n- \n\n", 'First item'],
        'Code' => ["\n\n```cpp\n", "\n```\n\n", 'code'],
        'Table' => ["\n\n| Parameter | Value |\n|---|---|\n| ", " |  |\n\n", 'Name'],
        'Note' => ["\n\n> [!NOTE]\n> ", "\n\n", 'Useful information.'],
        'Tip' => ["\n\n> [!TIP]\n> ", "\n\n", 'A helpful tip.'],
        'Warning' => ["\n\n> [!WARNING]\n> ", "\n\n", 'Something to be careful about.'],
    ];
@endphp
<div>
    <x-studio.page-header :title="$entityId ? 'Edit wiki page' : 'Add a wiki page'"
        intro="Write in Markdown on the left; the preview on the right shows how the page will look."
        :back="route('studio.wiki.index', array_filter(['product' => $form['product_id']]))" back-label="Wiki" />

    <div class="flex flex-col gap-6 pb-28">
        @if ($revision?->needs_rebase)
            <x-studio.alert variant="warning" title="This page changed while you were editing">{{ $revision->rebase_note }}</x-studio.alert>
        @endif
        @if ($revision?->status === \App\Enums\RevisionStatus::Rejected)
            <x-studio.alert variant="danger" title="The Content Admin asked for changes">{{ $revision->comment }}</x-studio.alert>
        @endif

        <x-studio.card title="Page details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Product" for="w-product" required :error="$errors->first('form.product_id')"
                              :hint="$entityId ? 'A live page stays with its product.' : 'The page appears under this product in the wiki.'">
                    <select id="w-product" wire:model="form.product_id" class="{{ $control }} min-h-11" @disabled($entityId !== null)>
                        <option value="">Choose a product…</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->category?->name }})</option>
                        @endforeach
                    </select>
                </x-form.field>
                <x-studio.input label="Page title" model="form.title" required live placeholder="e.g. Quick Start" />
                <x-form.field label="Web address" for="w-slug" :error="$errors->first('form.slug')" hint="Filled in from the title.">
                    <input id="w-slug" type="text" wire:model.blur="form.slug" class="{{ $control }} min-h-11 font-mono" maxlength="255">
                </x-form.field>
                <x-studio.input label="Position in the list" model="form.sort_order" type="number" min="0" hint="Lower numbers come first." class="max-w-40" />
            </div>
        </x-studio.card>

        <div class="grid gap-6 xl:grid-cols-2">
            {{-- Editor --}}
            <x-studio.card title="Content (Markdown)">
                <div x-data="{
                        wrap(before, after, placeholder) {
                            const area = this.$refs.body;
                            const { selectionStart: start, selectionEnd: end, value } = area;
                            const selected = value.slice(start, end) || placeholder;
                            area.value = value.slice(0, start) + before + selected + after + value.slice(end);
                            area.focus();
                            area.setSelectionRange(start + before.length, start + before.length + selected.length);
                            area.dispatchEvent(new Event('input', { bubbles: true }));
                        },
                     }"
                     @insert-markdown.window="wrap('\n\n' + ($event.detail.text ?? $event.detail[0]?.text ?? '') + '\n\n', '', '')">
                    <div class="mb-2 flex flex-wrap gap-1.5" role="toolbar" aria-label="Formatting">
                        @foreach ($snippets as $name => [$before, $after, $placeholder])
                            <button type="button" class="{{ $tool }}" @click="wrap(@js($before), @js($after), @js($placeholder))">{{ $name }}</button>
                        @endforeach
                    </div>

                    <label for="w-body" class="sr-only">Page content in Markdown</label>
                    <textarea id="w-body" x-ref="body" rows="24" wire:model.live.debounce.500ms="form.body" spellcheck="true"
                              @error('form.body') aria-invalid="true" @enderror
                              class="{{ $control }} font-mono text-sm leading-6"></textarea>
                    @error('form.body')<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4 border-t border-neutral-200 pt-4">
                    <label for="w-attachment" class="text-sm font-medium text-neutral-900">Insert an image or a file download</label>
                    <input id="w-attachment" type="file" wire:model="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.zip,.step,.stp,.stl,.bin,.hex,.uf2,.txt,.csv"
                           class="mt-1 block w-full text-base text-neutral-700 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-primary-600 file:bg-white file:px-4 file:font-semibold file:text-primary-700 hover:file:bg-primary-50">
                    <p class="mt-1 text-sm text-neutral-600">The file is uploaded to the media library and a link is inserted where the cursor is.</p>
                    <p wire:loading wire:target="attachment" class="mt-1 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
                    @error('attachment')<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                </div>

                <details class="mt-4 rounded-control border border-neutral-200 bg-neutral-50 px-3 py-2">
                    <summary class="cursor-pointer text-sm font-semibold text-primary-700">Markdown cheat sheet</summary>
                    <pre class="mt-2 overflow-x-auto font-mono text-sm leading-6 text-neutral-700">## Section heading          ### Sub-heading
**bold**   *italic*   `inline code`
[link text](https://example.com)
- bullet item               1. numbered item

| Column | Column |          ```cpp
|---|---|                    code block
| cell | cell |              ```

> [!NOTE]      > [!TIP]      > [!WARNING]
> Text of the callout.</pre>
                </details>
            </x-studio.card>

            {{-- Live preview --}}
            <x-studio.card title="Preview" intro="Updates as you type.">
                <div wire:loading.class="opacity-60" wire:target="form.body" aria-live="off">
                    @if ($rendered['html'] === '')
                        <p class="text-base text-neutral-600">Nothing to preview yet. Start typing on the left.</p>
                    @else
                        <h2 class="text-2xl font-bold tracking-tight text-neutral-900">{{ $form['title'] ?: 'Page title' }}</h2>
                        <div class="rich-text wiki-content mt-4">{!! $rendered['html'] !!}</div>
                    @endif
                </div>
            </x-studio.card>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-neutral-200 bg-white lg:left-64">
        <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-end gap-2 px-4 py-3 sm:px-6 lg:px-8">
            <p wire:loading wire:target="saveDraft, submit" class="mr-auto text-sm font-semibold text-primary-700" role="status">Saving…</p>
            @if ($revision)
                <x-button :href="route('studio.revisions.preview', $revision)" variant="ghost" target="_blank" rel="noopener">
                    Full-page preview of last saved draft <x-icon name="external" class="h-4 w-4" /><span class="sr-only">(opens in a new tab)</span>
                </x-button>
            @endif
            <x-button variant="secondary" wire:click="saveDraft" wire:loading.attr="disabled">Save draft</x-button>
            <x-button wire:click="submit" wire:loading.attr="disabled">{{ $isAdmin ? 'Publish now' : 'Submit for approval' }}</x-button>
        </div>
    </div>
</div>
