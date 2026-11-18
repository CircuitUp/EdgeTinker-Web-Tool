{{-- Pick a background image or video from the media library, or upload a new one. --}}
<div x-show="library.open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="be-library-title" data-no-nudge
     @keydown.escape.window="library.open = false">
    <div class="absolute inset-0 bg-neutral-900/60" @click="library.open = false" aria-hidden="true"></div>
    <div class="relative flex max-h-[85vh] w-full max-w-3xl flex-col rounded-panel bg-white shadow-overlay" x-trap.noscroll="library.open">
        <div class="flex items-center justify-between border-b border-neutral-200 p-4">
            <h2 id="be-library-title" class="text-lg font-semibold" x-text="library.kind === 'video' ? 'Choose a background video' : 'Choose a background image'"></h2>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-control hover:bg-neutral-100" @click="library.open = false"><x-icon name="x" /><span class="sr-only">Close</span></button>
        </div>
        <div class="border-b border-neutral-200 p-4">
            <label class="block text-sm font-medium text-neutral-900" for="be-upload" x-text="library.kind === 'video' ? 'Upload an MP4 or WebM video (up to 30 MB)' : 'Upload a JPG, PNG, WebP or GIF (up to 8 MB)'"></label>
            <input id="be-upload" type="file" class="mt-2 block w-full text-sm" :accept="library.kind === 'video' ? '.mp4,.webm' : '.jpg,.jpeg,.png,.webp,.gif'" @change="uploadMedia($event)" :disabled="library.uploading">
            <p x-show="library.uploading" class="mt-2 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
            <p x-show="library.error" class="mt-2 text-sm font-medium text-danger" role="alert" x-text="library.error"></p>
        </div>
        <div class="overflow-y-auto p-4">
            <p x-show="library.loading" class="text-sm text-neutral-600" role="status">Loading the media library…</p>
            <p x-show="!library.loading && !library.items.length" class="text-sm text-neutral-600">Nothing here yet. Upload a file above.</p>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <template x-for="item in library.items" :key="item.id">
                    <li>
                        <button type="button" class="block w-full overflow-hidden rounded-card border border-neutral-200 text-left hover:border-primary-600 focus-visible:border-primary-600" @click="useMedia(item)">
                            <template x-if="item.kind === 'image'"><img :src="item.thumb" alt="" class="aspect-[4/3] w-full bg-neutral-100 object-cover" loading="lazy"></template>
                            <template x-if="item.kind === 'video'"><video :src="item.url" class="aspect-[4/3] w-full bg-neutral-900 object-cover" muted preload="metadata"></video></template>
                            <span class="block truncate px-2 py-1 text-sm" x-text="item.name"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
