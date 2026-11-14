<div>
    <x-studio.page-header title="Site settings" intro="These apply to the whole site as soon as you save." />

    <form wire:submit="save" class="flex max-w-3xl flex-col gap-6">
        <x-studio.card title="Site name and logo">
            <div class="grid gap-5">
                <x-studio.input label="Site name" model="form.site_name" required />
                <x-studio.input label="Tagline" model="form.site_tagline" hint="Shown in the footer and in search-engine results for the home page." />

                <div>
                    <p class="text-sm font-medium text-neutral-900">Logo</p>
                    <p class="text-sm text-neutral-600">Without a logo the site shows the text wordmark “EdgeTinker”. A transparent PNG about 64 px high works best.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-4">
                        <div class="flex h-14 items-center rounded-control border border-neutral-200 bg-white px-4">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Current logo" class="h-8 w-auto">
                            @else
                                <span class="text-2xl font-bold tracking-tight text-neutral-900">Edge<span class="text-primary-600">Tinker</span></span>
                            @endif
                        </div>
                        @if ($logoUrl)
                            <x-button variant="ghost-danger" size="sm" wire:click="removeLogo" wire:confirm="Remove the logo and show the text wordmark instead?">Remove logo</x-button>
                        @endif
                    </div>
                    <label for="logo-upload" class="sr-only">Upload a logo</label>
                    <input id="logo-upload" type="file" wire:model="logoUpload" accept=".png,.jpg,.jpeg,.webp,.gif"
                           class="mt-3 block w-full text-base text-neutral-700 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-primary-600 file:bg-white file:px-4 file:font-semibold file:text-primary-700 hover:file:bg-primary-50">
                    <p wire:loading wire:target="logoUpload" class="mt-1 text-sm font-semibold text-primary-700" role="status">Uploading…</p>
                    @error('logoUpload')<p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-studio.card>

        <x-studio.card title="Contact details" intro="Shown on the Contact page and in the footer. Until you fill these in, the site shows clearly marked placeholders.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-studio.input label="Public email address" model="form.contact_email" type="email" placeholder="hello@edgetinker.com" />
                <x-studio.input label="Phone" model="form.contact_phone" placeholder="+94 11 000 0000" />
                <x-form.field label="Address" for="f-address" class="sm:col-span-2" :error="$errors->first('form.contact_address')">
                    <textarea id="f-address" rows="2" wire:model="form.contact_address" maxlength="300"
                              class="block w-full rounded-control border border-neutral-300 bg-white px-3 py-2 text-base hover:border-neutral-400"></textarea>
                </x-form.field>
                <x-studio.input label="Map embed address" model="form.contact_map_embed_url" class="sm:col-span-2" mono placeholder="https://www.google.com/maps/embed?pb=…"
                                hint="In Google Maps: Share → Embed a map, then copy only the web address that follows src= (without the quotation marks). Leave empty to show no map." />
                <x-studio.input label="Send contact-form messages to" model="form.contact_recipient" type="email" class="sm:col-span-2"
                                :hint="'Leave empty to use the public email address. Messages currently go to: '.($recipient ?? 'nowhere yet (they are still saved under Messages)').'.'" />
            </div>
        </x-studio.card>

        <x-studio.card title="Social links" intro="Full addresses starting with https://. Empty ones are not shown.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-studio.input label="LinkedIn" model="form.social_linkedin" type="url" placeholder="https://www.linkedin.com/company/…" />
                <x-studio.input label="GitHub" model="form.social_github" type="url" placeholder="https://github.com/…" />
                <x-studio.input label="YouTube" model="form.social_youtube" type="url" placeholder="https://www.youtube.com/@…" />
                <x-studio.input label="Facebook" model="form.social_facebook" type="url" placeholder="https://www.facebook.com/…" />
            </div>
        </x-studio.card>

        <div class="flex justify-end">
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save settings</x-button>
        </div>
    </form>

    <div class="mt-6 max-w-3xl">
        <x-studio.card title="Email test" intro="Checks that the site can send email (notifications, password resets, contact messages).">
            <p class="text-base text-neutral-700">
                Mailer: <span class="font-mono font-medium text-neutral-900">{{ $mailer }}</span> ·
                From: <span class="font-mono font-medium text-neutral-900">{{ $mailFrom }}</span>.
                These come from the <span class="font-mono">.env</span> file on the server, not from this screen.
            </p>
            <x-button variant="secondary" class="mt-3" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail">Send a test email to me</x-button>
            <p wire:loading wire:target="sendTestEmail" class="mt-2 text-sm font-semibold text-primary-700" role="status">Sending…</p>
            @if ($mailResult)
                <x-studio.alert :variant="$mailOk ? 'success' : 'danger'" class="mt-3">{{ $mailResult }}</x-studio.alert>
            @endif
        </x-studio.card>
    </div>
</div>
