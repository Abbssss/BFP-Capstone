<x-guest-layout>
    <p class="auth-kicker">Welcome back</p>
    <h2>Sign in to FIRE-PMIS</h2>
    <p class="auth-description">Use your assigned station credentials.</p>
    <x-auth-session-status class="auth-status" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Enter your station email">
            @if ($errors->has('email'))
                <div class="auth-error">{{ $errors->first('email') }}</div>
            @endif
        </div>

        <div class="auth-field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            @if ($errors->has('password'))
                <div class="auth-error">{{ $errors->first('password') }}</div>
            @endif
        </div>

        <div class="auth-options">
            <label class="auth-check" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
            @endif
        </div>

        <button class="auth-submit" type="submit">{{ __('Sign in securely') }} <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-description" style="margin: 18px 0 0; text-align: center">Need an account? <a class="auth-link" href="{{ route('register') }}">Request access</a></p>
</x-guest-layout>
