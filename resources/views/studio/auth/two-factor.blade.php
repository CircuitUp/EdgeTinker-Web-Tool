<x-layouts.studio-auth title="Two-factor code">
    <form method="POST" action="{{ route('studio.two-factor.challenge') }}" class="flex flex-col gap-4" x-data="{ recovery: {{ $errors->has('recovery_code') || old('recovery_code') ? 'true' : 'false' }} }" novalidate>
        @csrf
        <div x-show="! recovery">
            <x-form.field label="6-digit code from your authenticator app" for="code" :error="$errors->first('code')">
                <x-form.input id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" mono autofocus :invalid="$errors->has('code')" />
            </x-form.field>
        </div>
        <div x-show="recovery" x-cloak>
            <x-form.field label="Recovery code" for="recovery_code" hint="Each recovery code works once.">
                <x-form.input id="recovery_code" autocomplete="off" mono />
            </x-form.field>
        </div>
        <x-button type="submit" class="w-full">Verify and sign in</x-button>
        <button type="button" class="min-h-9 text-sm font-semibold text-primary-700 underline-offset-2 hover:underline" @click="recovery = ! recovery">
            <span x-show="! recovery">Use a recovery code instead</span>
            <span x-show="recovery" x-cloak>Use the authenticator app instead</span>
        </button>
    </form>
    @vite(['resources/js/app.js'])

    <x-slot:footer>
        <a href="{{ route('studio.login') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Back to sign in</a>
    </x-slot:footer>
</x-layouts.studio-auth>
