<x-layouts.studio-auth title="Register as a Content Creator">
    <p class="mb-4 text-base text-neutral-600">
        After you register you will be asked to confirm your email address. The Content Admin then activates your account before you can sign in.
    </p>

    <form method="POST" action="{{ route('studio.register') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <x-form.field label="Full name" for="name" :error="$errors->first('name')">
            <x-form.input id="name" :value="old('name')" autocomplete="name" required autofocus :invalid="$errors->has('name')" />
        </x-form.field>
        <x-form.field label="Work email address" for="email" :error="$errors->first('email')">
            <x-form.input id="email" type="email" :value="old('email')" autocomplete="email" required :invalid="$errors->has('email')" />
        </x-form.field>
        <x-form.field label="Password" for="password" :error="$errors->first('password')"
                      hint="At least 10 characters, with upper and lower case letters and a number.">
            <x-form.input id="password" type="password" autocomplete="new-password" required :invalid="$errors->has('password')" />
        </x-form.field>
        <x-form.field label="Repeat password" for="password_confirmation">
            <x-form.input id="password_confirmation" type="password" autocomplete="new-password" required />
        </x-form.field>

        {{-- Honeypot: hidden from people; automated form-fillers tend to complete it. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <x-button type="submit" class="w-full">Register</x-button>
    </form>

    <x-slot:footer>
        Already registered? <a href="{{ route('studio.login') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Sign in</a>
    </x-slot:footer>
</x-layouts.studio-auth>
