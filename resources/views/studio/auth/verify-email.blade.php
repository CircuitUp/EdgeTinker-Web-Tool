<x-layouts.studio-auth title="Check your inbox">
    <div class="flex flex-col gap-3 text-base text-neutral-700">
        <p>We have sent you an email with a link to confirm your address. The link is valid for 24 hours.</p>
        <p>Once you have confirmed it, the Content Admin will review and activate your account. You will get an email when you can sign in.</p>
    </div>

    <form method="POST" action="{{ route('studio.verification.resend') }}" class="mt-5">
        @csrf
        <x-button type="submit" variant="secondary" class="w-full">Send the email again</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('studio.login') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Back to sign in</a>
    </x-slot:footer>
</x-layouts.studio-auth>
