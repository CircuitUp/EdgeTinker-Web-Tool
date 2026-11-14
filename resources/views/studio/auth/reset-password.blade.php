<x-layouts.studio-auth title="Choose a new password">
    <form method="POST" action="{{ route('studio.password.update') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.field label="Email address" for="email" :error="$errors->first('email')">
            <x-form.input id="email" type="email" :value="old('email', $email)" autocomplete="username" required :invalid="$errors->has('email')" />
        </x-form.field>
        <x-form.field label="New password" for="password" :error="$errors->first('password')"
                      hint="At least 10 characters, with upper and lower case letters and a number.">
            <x-form.input id="password" type="password" autocomplete="new-password" required autofocus :invalid="$errors->has('password')" />
        </x-form.field>
        <x-form.field label="Repeat new password" for="password_confirmation">
            <x-form.input id="password_confirmation" type="password" autocomplete="new-password" required />
        </x-form.field>
        <x-button type="submit" class="w-full">Change password</x-button>
    </form>
</x-layouts.studio-auth>
