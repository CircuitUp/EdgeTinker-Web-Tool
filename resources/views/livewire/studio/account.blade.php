<div>
    <x-studio.page-header title="Account" :intro="$user->name.' · '.$user->email.' · '.$user->role->label()" />

    <div class="flex max-w-3xl flex-col gap-6">
        <x-studio.card title="Change password">
            <form wire:submit="changePassword" class="grid gap-5">
                <x-studio.input label="Current password" model="currentPassword" type="password" autocomplete="current-password" required />
                <x-studio.input label="New password" model="newPassword" type="password" autocomplete="new-password" required
                                hint="At least 10 characters, with upper and lower case letters and a number." />
                <x-studio.input label="Repeat new password" model="newPasswordConfirmation" type="password" autocomplete="new-password" required />
                <div><x-button type="submit" wire:loading.attr="disabled">Change password</x-button></div>
            </form>
        </x-studio.card>

        @if ($user->isAdmin())
            <x-studio.card title="Two-factor authentication" intro="Optional. Adds a 6-digit code from an authenticator app when you sign in.">
                @if ($shownRecoveryCodes)
                    <x-studio.alert variant="warning" title="Save these recovery codes now" class="mb-5">
                        <p>Each code lets you sign in once if you lose your phone. They are shown only this one time. Keep them somewhere safe, such as a password manager.</p>
                        <ul class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-base">
                            @foreach ($shownRecoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach
                        </ul>
                    </x-studio.alert>
                @endif

                @if ($settingUp && $qrCode)
                    <ol class="flex list-decimal flex-col gap-4 pl-5 text-base text-neutral-900">
                        <li>
                            Open an authenticator app (for example Google Authenticator, Microsoft Authenticator or Aegis) and scan this code.
                            <div class="mt-3 inline-block rounded-card border border-neutral-200 bg-white p-2">{!! $qrCode !!}</div>
                            <p class="mt-2 text-sm text-neutral-600">Cannot scan? Enter this key by hand: <span class="break-all font-mono text-neutral-900">{{ $secret }}</span></p>
                        </li>
                        <li>
                            Enter the 6-digit code the app shows.
                            <form wire:submit="confirmTwoFactor" class="mt-2 flex max-w-xs items-end gap-2">
                                <div class="flex-1">
                                    <label for="two-factor-code" class="sr-only">6-digit code</label>
                                    <input id="two-factor-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" wire:model="code"
                                           class="block min-h-11 w-full rounded-control border border-neutral-300 bg-white px-3 font-mono text-base hover:border-neutral-400" @error('code') aria-invalid="true" @enderror>
                                </div>
                                <x-button type="submit">Turn on</x-button>
                            </form>
                            @error('code')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                        </li>
                    </ol>
                    <x-button variant="ghost" class="mt-4" wire:click="cancelTwoFactor">Cancel</x-button>
                @elseif ($twoFactorOn)
                    <p class="flex flex-wrap items-center gap-2 text-base text-neutral-900">
                        <x-badge variant="success">On</x-badge>
                        You have {{ $recoveryCodesLeft }} unused recovery {{ Str::plural('code', $recoveryCodesLeft) }}.
                    </p>
                    <div class="mt-4 max-w-sm">
                        <x-studio.input label="Your password, to confirm a change below" model="confirmPassword" type="password" autocomplete="current-password" />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button variant="secondary" wire:click="regenerateRecoveryCodes">Generate new recovery codes</x-button>
                        <x-button variant="ghost-danger" wire:click="disableTwoFactor"
                                  wire:confirm="Turn off two-factor authentication? Your account will be protected by your password only.">Turn off</x-button>
                    </div>
                @else
                    <p class="flex flex-wrap items-center gap-2 text-base text-neutral-900"><x-badge>Off</x-badge> Your account is protected by your password only.</p>
                    <x-button class="mt-4" wire:click="startTwoFactor">Set up two-factor authentication</x-button>
                @endif
            </x-studio.card>
        @endif
    </div>
</div>
