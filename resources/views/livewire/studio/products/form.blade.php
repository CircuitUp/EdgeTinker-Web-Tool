@php
    $isNew = $entityId === null;
    $label = 'text-sm font-medium text-neutral-900';
    $control = 'block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500 hover:border-neutral-400';
    $iconButton = 'inline-flex h-10 w-10 items-center justify-center rounded-control text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 disabled:opacity-40';
    $imageUrl = fn (array $image) => ($image['media_id'] ?? null)
        ? (isset($media[$image['media_id']]) ? $media[$image['media_id']]->variantUrl(400) : null)
        : media_url($image['path'] ?? null);
@endphp
<div>
    <x-studio.page-header :title="$isNew ? 'Add a product' : 'Edit product'"
        :intro="$isNew
            ? 'Fill in the basics and add at least one image. The web address and search-engine fields fill themselves in.'
            : 'Editing “'.($liveName ?? $form['name']).'”. The live page does not change until '.($isAdmin ? 'you publish.' : 'the Content Admin approves your changes.')"
        :back="route('studio.products.index')" back-label="All products" />

    <div class="flex flex-col gap-6 pb-28">
        @if ($revision?->needs_rebase)
            <x-studio.alert variant="warning" title="This product changed while you were editing">{{ $revision->rebase_note }}</x-studio.alert>
        @endif
        @if ($revision?->status === \App\Enums\RevisionStatus::Rejected)
            <x-studio.alert variant="danger" title="The Content Admin asked for changes">{{ $revision->comment }}</x-studio.alert>
        @endif
        @if ($otherPending->isNotEmpty())
            <x-studio.alert variant="warning" title="Someone else also has changes pending for this product">
                {{ $otherPending->map(fn ($r) => ($r->author?->name ?? 'A deleted user').' ('.strtolower($r->status->label()).')')->join(', ') }}.
                Whichever is approved first goes live; the other will need to be updated.
            </x-studio.alert>
        @endif

        {{-- Basics ----------------------------------------------------------- --}}
        <x-studio.card title="Basics">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-studio.input label="Product name" model="form.name" required live class="sm:col-span-2" placeholder="e.g. EdgeCore ESP32-S3 Dev Board" />

                <x-form.field label="Category" for="f-category" required :error="$errors->first('form.category_id')">
                    <select id="f-category" wire:model="form.category_id" class="{{ $control }}" @if ($errors->has('form.category_id')) aria-invalid="true" @endif>
                        <option value="">Choose a category…</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-studio.input label="SKU" model="form.sku" required mono placeholder="ET-DEV-S3-01" hint="Your part number. Must be unique." />

                <x-form.field label="Short description" for="f-short" required class="sm:col-span-2" :error="$errors->first('form.short_description')"
                              hint="One sentence, shown on product cards and at the top of the product page.">
                    <textarea id="f-short" rows="2" maxlength="500" wire:model="form.short_description" class="{{ $control }}"></textarea>
                </x-form.field>

                <x-studio.input label="Price in USD" model="form.price_usd" type="number" step="0.01" min="0" mono placeholder="14.90" hint="Leave empty to show “Price on request”." />
                <x-studio.input label="Price in LKR" model="form.price_lkr" type="number" step="0.01" min="0" mono placeholder="4500" hint="Entered by hand; there is no automatic conversion." />

                <x-form.field label="Stock status" for="f-stock" required :error="$errors->first('form.stock_status')">
                    <select id="f-stock" wire:model="form.stock_status" class="{{ $control }}">
                        @foreach ($stockStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-studio.input label="Store link" model="form.store_url" type="url" mono placeholder="https://store.example.com/your-product"
                                hint="The page where this product can be bought. When the product is in stock (or low stock), its page shows a “Buy” button that opens this link in a new tab. Out of stock, coming soon, or no link: the button reads “Send Inquiry” and opens the contact form." />

                <div class="flex items-end sm:col-span-2">
                    <label for="f-featured" class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-base text-neutral-900">
                        <input id="f-featured" type="checkbox" wire:model="form.is_featured" class="h-5 w-5 accent-primary-600">
                        <span>Feature this product on the home page</span>
                    </label>
                </div>
            </div>
        </x-studio.card>

        {{-- Images ----------------------------------------------------------- --}}
        <x-studio.card title="Images" intro="Drag files onto the box or click it. Drag images to reorder them; choose which one is the thumbnail.">
            <x-slot:actions>
                <x-button variant="secondary" size="sm" wire:click="$set('showLibrary', true)">Choose from media library</x-button>
            </x-slot:actions>

            <div x-data="{ over: false }" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop="over = false"
                 :class="over ? 'border-primary-600 bg-primary-50' : 'border-neutral-300 bg-neutral-50'"
                 class="relative flex flex-col items-center justify-center rounded-card border-2 border-dashed px-4 py-8 text-center has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary-600">
                <x-icon name="box" class="h-8 w-8 text-primary-600" />
                <p class="mt-2 text-base font-medium text-neutral-900">Drop images here, or click to choose files</p>
                <p class="text-sm text-neutral-600">JPG, PNG, WebP or GIF, up to 8 MB each. Up to 12 images.</p>
                {{-- The real file input covers the whole box, so both clicking and dropping work natively. --}}
                <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.gif" wire:model="imageUploads" aria-label="Upload product images"
                       class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                <p wire:loading wire:target="imageUploads" class="mt-2 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
            </div>
            @foreach (['imageUploads', 'imageUploads.*', 'form.images'] as $key)
                @error($key)<p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-danger"><x-icon name="alert" class="h-4 w-4" />{{ $message }}</p>@enderror
            @endforeach

            @if (! empty($form['images']))
                <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-data="sortable({ method: 'moveImage' })">
                    @foreach ($form['images'] as $i => $image)
                        <li wire:key="image-{{ $i }}-{{ $image['media_id'] ?? md5($image['path'] ?? '') }}" draggable="true"
                            @dragstart="start({{ $i }}, $event)" @dragover.prevent @drop.prevent="drop({{ $i }})"
                            class="flex flex-col gap-3 rounded-card border bg-white p-3 {{ $image['is_thumbnail'] ? 'border-primary-600 ring-2 ring-primary-200' : 'border-neutral-200' }}">
                            <div class="aspect-square cursor-grab overflow-hidden rounded-control bg-neutral-50">
                                @if ($url = $imageUrl($image))
                                    <img src="{{ $url }}" alt="" class="h-full w-full object-contain" draggable="false">
                                @endif
                            </div>
                            <div>
                                <label for="image-alt-{{ $i }}" class="{{ $label }}">Description for screen readers</label>
                                <input id="image-alt-{{ $i }}" type="text" wire:model="form.images.{{ $i }}.alt" class="{{ $control }} mt-1" maxlength="255">
                                @error("form.images.$i.alt")<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-sm font-medium text-neutral-900">
                                    <input type="radio" name="thumbnail" @checked($image['is_thumbnail']) wire:click="setThumbnail({{ $i }})" class="h-4 w-4 accent-primary-600">
                                    Thumbnail
                                </label>
                                <div class="flex items-center">
                                    <button type="button" class="{{ $iconButton }}" wire:click="moveImage({{ $i }}, {{ $i - 1 }})" @disabled($loop->first)><x-icon name="chevron-left" /><span class="sr-only">Move image {{ $i + 1 }} earlier</span></button>
                                    <button type="button" class="{{ $iconButton }}" wire:click="moveImage({{ $i }}, {{ $i + 1 }})" @disabled($loop->last)><x-icon name="chevron-right" /><span class="sr-only">Move image {{ $i + 1 }} later</span></button>
                                    <button type="button" class="{{ $iconButton }} hover:text-danger" wire:click="removeImage({{ $i }})"><x-icon name="x" /><span class="sr-only">Remove image {{ $i + 1 }}</span></button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-studio.card>

        {{-- Description ------------------------------------------------------ --}}
        <x-studio.card title="Full description" intro="Shown in the Overview tab of the product page.">
            {{-- wire:ignore: Livewire must not re-render the editor while someone is typing in it. --}}
            <div wire:ignore x-data="richText({ model: 'form.description' })">
                <input id="description-input" type="hidden" x-ref="input" value="{{ $form['description'] }}">
                <trix-editor input="description-input" x-ref="editor" aria-label="Full description"></trix-editor>
            </div>
            @error('form.description')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        </x-studio.card>

        {{-- Key features ----------------------------------------------------- --}}
        <x-studio.card title="Key features" intro="Short bullet points. Empty rows are ignored.">
            <ul class="flex flex-col gap-2">
                @foreach ($form['key_features'] as $i => $feature)
                    <li wire:key="feature-{{ $i }}" class="flex items-center gap-2">
                        <label for="feature-{{ $i }}" class="sr-only">Key feature {{ $i + 1 }}</label>
                        <input id="feature-{{ $i }}" type="text" wire:model="form.key_features.{{ $i }}" class="{{ $control }}" maxlength="300" placeholder="e.g. USB-C with native USB">
                        <button type="button" class="{{ $iconButton }} hover:text-danger" wire:click="removeFeature({{ $i }})"><x-icon name="x" /><span class="sr-only">Remove key feature {{ $i + 1 }}</span></button>
                    </li>
                @endforeach
            </ul>
            <x-button variant="secondary" size="sm" class="mt-3" wire:click="addFeature">Add a feature</x-button>
        </x-studio.card>

        {{-- Specifications --------------------------------------------------- --}}
        <x-studio.card title="Specifications" intro="One row per parameter. The first four also appear beside the product image.">
            @if (! empty($form['specs']))
                <div class="hidden grid-cols-[1fr_1.5fr_7.5rem] gap-2 pb-1 text-sm font-medium text-neutral-600 sm:grid">
                    <span>Parameter</span><span>Value</span><span class="sr-only">Actions</span>
                </div>
            @endif
            <ul class="flex flex-col gap-2">
                @foreach ($form['specs'] as $i => $spec)
                    <li wire:key="spec-{{ $i }}" class="grid gap-2 rounded-control border border-neutral-200 p-2 sm:grid-cols-[1fr_1.5fr_7.5rem] sm:border-0 sm:p-0">
                        <div>
                            <label for="spec-label-{{ $i }}" class="sr-only">Parameter {{ $i + 1 }}</label>
                            <input id="spec-label-{{ $i }}" type="text" wire:model="form.specs.{{ $i }}.label" class="{{ $control }}" placeholder="e.g. Input voltage" maxlength="255">
                            @error("form.specs.$i.label")<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="spec-value-{{ $i }}" class="sr-only">Value {{ $i + 1 }}</label>
                            <input id="spec-value-{{ $i }}" type="text" wire:model="form.specs.{{ $i }}.value" class="{{ $control }} font-mono" placeholder="e.g. 7–36 V DC" maxlength="500">
                            @error("form.specs.$i.value")<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex items-center justify-end">
                            <button type="button" class="{{ $iconButton }}" wire:click="moveSpec({{ $i }}, {{ $i - 1 }})" @disabled($loop->first)><x-icon name="chevron-left" class="rotate-90" /><span class="sr-only">Move row {{ $i + 1 }} up</span></button>
                            <button type="button" class="{{ $iconButton }}" wire:click="moveSpec({{ $i }}, {{ $i + 1 }})" @disabled($loop->last)><x-icon name="chevron-right" class="rotate-90" /><span class="sr-only">Move row {{ $i + 1 }} down</span></button>
                            <button type="button" class="{{ $iconButton }} hover:text-danger" wire:click="removeSpec({{ $i }})"><x-icon name="x" /><span class="sr-only">Remove row {{ $i + 1 }}</span></button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-button variant="secondary" size="sm" class="mt-3" wire:click="addSpec">Add a row</x-button>
        </x-studio.card>

        {{-- Documents -------------------------------------------------------- --}}
        <x-studio.card title="Documents and downloads" intro="Datasheet, schematic, pinout, 3D model, firmware.">
            @if (! empty($form['documents']))
                <ul class="mb-4 flex flex-col gap-3">
                    @foreach ($form['documents'] as $i => $document)
                        @php $file = $media[$document['media_id']] ?? null; @endphp
                        <li wire:key="document-{{ $i }}-{{ $document['media_id'] }}" class="grid gap-2 rounded-control border border-neutral-200 p-3 sm:grid-cols-[1.5fr_1fr_2.5rem] sm:items-start">
                            <div>
                                <label for="document-title-{{ $i }}" class="{{ $label }}">Title</label>
                                <input id="document-title-{{ $i }}" type="text" wire:model="form.documents.{{ $i }}.title" class="{{ $control }} mt-1" maxlength="255">
                                <p class="mt-1 truncate font-mono text-sm text-neutral-600">{{ $file?->original_name ?? 'File missing' }}{{ $file ? ' · '.$file->humanSize() : '' }}</p>
                                @error("form.documents.$i.title")<p class="text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="document-type-{{ $i }}" class="{{ $label }}">Type</label>
                                <select id="document-type-{{ $i }}" wire:model="form.documents.{{ $i }}.type" class="{{ $control }} mt-1">
                                    @foreach ($documentTypes as $value => $name)
                                        <option value="{{ $value }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="{{ $iconButton }} hover:text-danger sm:mt-6" wire:click="removeDocument({{ $i }})"><x-icon name="x" /><span class="sr-only">Remove document {{ $i + 1 }}</span></button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <label for="document-upload" class="{{ $label }}">Add a document</label>
            <input id="document-upload" type="file" wire:model="documentUpload" accept=".pdf,.zip,.step,.stp,.stl,.bin,.hex,.uf2,.txt,.csv"
                   class="mt-1 block w-full text-base text-neutral-700 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-primary-600 file:bg-white file:px-4 file:font-semibold file:text-primary-700 hover:file:bg-primary-50">
            <p class="mt-1 text-sm text-neutral-600">PDF, ZIP, STEP, STL, BIN, HEX, UF2, TXT or CSV, up to 30 MB.</p>
            <p wire:loading wire:target="documentUpload" class="mt-1 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
            @error('documentUpload')<p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-danger"><x-icon name="alert" class="h-4 w-4" />{{ $message }}</p>@enderror
        </x-studio.card>

        {{-- Tags ------------------------------------------------------------- --}}
        <x-studio.card title="Tags" intro="Tags power the filters on listing pages. Pick existing tags, or create one.">
            @if ($tags->isEmpty() && empty($form['new_tags']))
                <p class="text-base text-neutral-600">There are no tags yet. Create the first one below.</p>
            @endif
            <ul class="flex flex-wrap gap-2">
                @foreach ($tags as $tag)
                    @php $selected = in_array($tag->id, $form['tag_ids'], true); @endphp
                    <li wire:key="tag-{{ $tag->id }}">
                        <button type="button" wire:click="toggleTag({{ $tag->id }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium {{ $selected ? 'bg-primary-600 text-white' : 'bg-primary-100 text-primary-800 hover:bg-primary-200' }}">
                            @if ($selected)<x-icon name="check" class="h-4 w-4" />@endif {{ $tag->name }}
                        </button>
                    </li>
                @endforeach
                @foreach ($form['new_tags'] as $i => $name)
                    <li wire:key="new-tag-{{ $i }}">
                        <button type="button" wire:click="removeNewTag({{ $i }})"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-dashed border-primary-600 bg-white px-3 text-sm font-medium text-primary-800 hover:bg-primary-50">
                            {{ $name }} <span class="text-neutral-600">(new)</span> <x-icon name="x" class="h-4 w-4" /><span class="sr-only">Remove new tag</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex max-w-md items-end gap-2">
                <div class="flex-1">
                    <label for="new-tag" class="{{ $label }}">Create a tag</label>
                    <input id="new-tag" type="text" wire:model="newTag" wire:keydown.enter.prevent="addNewTag" maxlength="60" class="{{ $control }} mt-1" placeholder="e.g. LoRaWAN">
                </div>
                <x-button variant="secondary" wire:click="addNewTag">Add</x-button>
            </div>
            @unless ($isAdmin)
                <p class="mt-2 text-sm text-neutral-600">A new tag is created when this product is approved.</p>
            @endunless
        </x-studio.card>

        {{-- Web address and SEO ---------------------------------------------- --}}
        <x-studio.card title="Web address and search engines" intro="These fill themselves in. Change them only if you need to.">
            <div class="grid gap-5">
                <x-form.field label="Web address" for="f-slug" :error="$errors->first('form.slug')" hint="Lower-case letters, numbers and dashes.">
                    <div class="flex items-center gap-2">
                        <span class="shrink-0 font-mono text-sm text-neutral-600">/product/</span>
                        <input id="f-slug" type="text" wire:model.blur="form.slug" class="{{ $control }} font-mono" maxlength="255">
                    </div>
                </x-form.field>
                <x-studio.input label="Title for search engines" model="form.seo_title" :placeholder="($form['name'] ?: 'Product name').' (filled in automatically)'" />
                <x-form.field label="Description for search engines" for="f-seo-description" :error="$errors->first('form.seo_description')">
                    <textarea id="f-seo-description" rows="2" maxlength="500" wire:model="form.seo_description" class="{{ $control }}"
                              placeholder="Uses the short description unless you write something here."></textarea>
                </x-form.field>
            </div>
        </x-studio.card>
    </div>

    {{-- Actions (always visible) --------------------------------------------- --}}
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

    {{-- Media library picker ------------------------------------------------- --}}
    @if ($showLibrary)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="library-title"
             x-data x-trap.noscroll="true" @keydown.escape.window="$wire.set('showLibrary', false)">
            <div class="absolute inset-0 bg-neutral-900/60" wire:click="$set('showLibrary', false)" aria-hidden="true"></div>
            <div class="relative flex max-h-full w-full flex-col overflow-hidden rounded-panel bg-white shadow-overlay sm:max-w-3xl">
                <div class="flex items-center justify-between gap-4 border-b border-neutral-200 px-6 py-4">
                    <h2 id="library-title" class="text-xl font-semibold text-neutral-900">Choose an image</h2>
                    <button type="button" class="{{ $iconButton }}" wire:click="$set('showLibrary', false)"><x-icon name="x" /><span class="sr-only">Close</span></button>
                </div>
                <div class="overflow-y-auto p-6">
                    @if ($library->isEmpty())
                        <x-empty-state title="The media library is empty">Upload images with the box on the form, or from the Media library page.</x-empty-state>
                    @else
                        <ul class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                            @foreach ($library as $item)
                                <li>
                                    <button type="button" wire:click="addFromLibrary({{ $item->id }})"
                                            class="block w-full overflow-hidden rounded-control border border-neutral-200 bg-neutral-50 hover:border-primary-600">
                                        <img src="{{ $item->variantUrl(400) }}" alt="{{ $item->original_name }}" loading="lazy" class="aspect-square w-full object-contain">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
