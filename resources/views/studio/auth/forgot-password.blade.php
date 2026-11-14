<x-layouts.studio-auth title="Reset your password">
    <p class="mb-4 text-base text-neutral-600">Enter your email address and we will send you a link to choose a new password.</p>

    <form method="POST" action="{{ route('studio.password.email') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <x-form.field label="Email address" for="email" :error="$errors->first('email')">
            <x-form.input id="email" type="email" :value="old('email')" autocomplete="email" required autofocus :invalid="$errors->has('email')" />
        </x-form.field>
        <x-button type="submit" class="w-full">Send reset link</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('studio.login') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Back to sign in</a>
    </x-slot:footer>
</x-layouts.studio-auth>
