<x-layouts.studio-auth title="Sign in">
    <form method="POST" action="{{ route('studio.login') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <x-form.field label="Email address" for="email" :error="$errors->first('email')">
            <x-form.input id="email" type="email" :value="old('email')" autocomplete="username" required autofocus :invalid="$errors->has('email')" />
        </x-form.field>
        <x-form.field label="Password" for="password" :error="$errors->first('password')">
            <x-form.input id="password" type="password" autocomplete="current-password" required :invalid="$errors->has('password')" />
        </x-form.field>
        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>

    <x-slot:footer>
        <p><a href="{{ route('studio.password.request') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Forgot your password?</a></p>
        <p class="mt-2">New Content Creator? <a href="{{ route('studio.register') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Register for an account</a></p>
    </x-slot:footer>
</x-layouts.studio-auth>
