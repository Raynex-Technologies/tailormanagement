<x-layouts::auth>
    <div class="flex flex-col gap-6">
        {{-- Heading --}}
        <div class="text-center">
            <h1 class="text-3xl font-bold" style="color: #1E1F2E;">{{ __('Welcome Back!!') }}</h1>
        </div>

        {{-- Session Status --}}
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5" x-data="{ showPassword: false }">
            @csrf

            {{-- Email --}}
            <div>
                <div class="auth-input-wrapper">
                    <span class="auth-label">{{ __('Email') }}</span>
                    <i class="fa-duotone fa-envelope auth-input-icon"></i>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="auth-input"
                        placeholder="email@gmail.com"
                        required
                        autofocus
                        autocomplete="email"
                    />
                </div>
                @error('email')
                    <p class="mt-1.5 text-sm" style="color: #C44B4B;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <div class="auth-input-wrapper">
                    <span class="auth-label">{{ __('Password') }}</span>
                    <i class="fa-duotone fa-lock auth-input-icon"></i>
                    <input
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        class="auth-input"
                        placeholder="{{ __('Enter your password') }}"
                        required
                        autocomplete="current-password"
                    />
                    <button type="button" class="auth-input-toggle" @click="showPassword = !showPassword">
                        <i class="fa-duotone" :class="showPassword ? 'fa-eye' : 'fa-eye-slash'"></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 text-sm" style="color: #C44B4B;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Forgot Password --}}
            @if (Route::has('password.request'))
                <div class="text-right">
                    <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-semibold hover:underline" style="color: #3D3D3D;">
                        {{ __('Forgot Password?') }}
                    </a>
                </div>
            @endif

            {{-- Login Button --}}
            <button type="submit" class="auth-btn" data-test="login-button">
                {{ __('Login') }}
            </button>
        </form>

        {{-- Sign Up Link --}}
        @if (Route::has('register'))
            <div class="text-center text-sm" style="color: #8B7B6B;">
                {{ __('Don\'t have an account?') }}
                <a href="{{ route('register') }}" wire:navigate class="font-semibold hover:underline" style="color: #D4A574;">{{ __('Sign up') }}</a>
            </div>
        @endif
    </div>
</x-layouts::auth>
