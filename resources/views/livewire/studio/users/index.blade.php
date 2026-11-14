<div>
    <x-studio.page-header title="Users" intro="Content Creators register themselves, confirm their email address, and then wait here for you to activate them." />

    <div class="flex flex-col gap-6">
        <x-studio.card title="Content Admin">
            <p class="text-base text-neutral-900"><span class="font-semibold">{{ $admin->name }}</span> · {{ $admin->email }}</p>
            <p class="mt-1 text-sm text-neutral-600">
                There is exactly one Content Admin. It is created with the <code class="rounded-control bg-neutral-100 px-1.5 py-0.5 font-mono text-sm">php artisan studio:create-admin</code> command, never by registration.
                Two-factor authentication is {{ $admin->hasTwoFactorEnabled() ? 'on' : 'off' }}
                (<a href="{{ route('studio.account') }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">manage in Account</a>).
            </p>
        </x-studio.card>

        <x-studio.card :title="'Content Creators ('.$creators->count().')'" flush>
            @if ($creators->isEmpty())
                <div class="p-5">
                    <x-empty-state title="No Content Creators yet">
                        Ask your team to register at <span class="font-mono">{{ route('studio.register') }}</span>. They will appear here once they do.
                    </x-empty-state>
                </div>
            @else
                <ul class="divide-y divide-neutral-200">
                    @foreach ($creators as $creator)
                        <li wire:key="user-{{ $creator->id }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-base font-semibold text-neutral-900">{{ $creator->name }}</p>
                                <p class="truncate text-sm text-neutral-600">{{ $creator->email }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <x-badge :variant="$creator->status->variant()">{{ $creator->status->label() }}</x-badge>
                                    @unless ($creator->hasVerifiedEmail())<x-badge variant="warning">Email not confirmed</x-badge>@endunless
                                    <span class="text-sm text-neutral-600">
                                        Registered {{ $creator->created_at->diffForHumans() }}
                                        · {{ $creator->revisions_count }} {{ Str::plural('submission', $creator->revisions_count) }}
                                        · {{ $creator->last_login_at ? 'last signed in '.$creator->last_login_at->diffForHumans() : 'never signed in' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($creator->status !== \App\Enums\UserStatus::Active)
                                    <x-button size="sm" wire:click="activate({{ $creator->id }})">{{ $creator->status === \App\Enums\UserStatus::Suspended ? 'Reactivate' : 'Activate' }}<span class="sr-only"> {{ $creator->name }}</span></x-button>
                                @else
                                    <x-button variant="secondary" size="sm" wire:click="suspend({{ $creator->id }})"
                                              wire:confirm="Suspend {{ $creator->name }}? They will be signed out and unable to sign in until you reactivate them.">Suspend<span class="sr-only"> {{ $creator->name }}</span></x-button>
                                @endif
                                <x-button variant="ghost" size="sm" wire:click="sendPasswordReset({{ $creator->id }})">Send password reset<span class="sr-only"> to {{ $creator->name }}</span></x-button>
                                <x-button variant="ghost-danger" size="sm" wire:click="delete({{ $creator->id }})"
                                          wire:confirm="Delete the account of {{ $creator->name }}? This cannot be undone. Their past submissions stay in the history.">Delete<span class="sr-only"> {{ $creator->name }}</span></x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-studio.card>
    </div>
</div>
