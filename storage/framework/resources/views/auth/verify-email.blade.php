<x-guest-layout>
    <x-slot name="title">{{ __('Verify email') }}</x-slot>
    <x-slot name="heading">{{ __('Verify your email') }}</x-slot>
    <x-slot name="subheading">{{ __('Thanks for signing up. Please verify your email address.') }}</x-slot>

    <div class="mb-4 text-sm text-gray-600">
        {{ __('We sent a verification link to your email. Click the link to verify your address. If you didn\'t receive the email, we can send another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided.') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button type="submit">
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 no-underline focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
