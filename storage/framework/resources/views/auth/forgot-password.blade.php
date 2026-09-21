<x-guest-layout>
    <x-slot name="title">{{ __('Forgot password') }}</x-slot>
    <x-slot name="heading">{{ __('Forgot your password?') }}</x-slot>
    <x-slot name="subheading">{{ __('Enter your email and we will send you a link to reset your password.') }}</x-slot>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('status') && session('mail_driver') === 'log')
        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
            <strong>{{ __('Development mode') }}</strong><br>
            {{ __('Emails are not sent; they are written to the log. To get the reset link:') }}
            <ol class="list-decimal list-inside mt-1 space-y-0.5">
                <li>{{ __('Open') }} <code class="bg-amber-100 px-1 rounded">storage/logs/laravel.log</code></li>
                <li>{{ __('Search for your email or "password reset" to find the reset URL.') }}</li>
            </ol>
            {{ __('To receive real emails, set') }} <code class="bg-amber-100 px-1 rounded">MAIL_MAILER=smtp</code> {{ __('and configure SMTP in') }} <code class="bg-amber-100 px-1 rounded">.env</code>.
        </div>
    @elseif (session('status'))
        <p class="mb-4 text-sm text-gray-600">{{ __('If you don\'t see the email, check your spam or junk folder.') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a class="text-sm text-gray-600 hover:text-gray-900 no-underline" href="{{ route('login') }}">
                {{ __('Back to sign in') }}
            </a>
            <x-primary-button type="submit">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
