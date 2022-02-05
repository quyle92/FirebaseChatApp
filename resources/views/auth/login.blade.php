<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="player_name" :value="__('player_name')" />

                <x-input id="player_name" class="block mt-1 w-full" type="text" name="player_name" :value="old('player_name')" required autofocus />
            </div>

            <!-- facebook_id -->
            <div class="mt-4">
                <x-label for="facebook_id" :value="__('facebook_id')" />

                <x-input id="facebook_id" class="block mt-1 w-full" type="password" name="facebook_id" required autocomplete="current-facebook_id" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" name="remember">
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('facebook_id.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('facebook_id.request') }}">
                    {{ __('Forgot your facebook_id?') }}
                </a>
                @endif

                <x-button class="ml-3">
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>