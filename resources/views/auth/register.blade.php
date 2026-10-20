<x-guest-layout>
    <p class="auth-kicker">Station access</p>
    <h2>Create your account</h2>
    <p class="auth-description">Register with your official personnel details.</p>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="name">{{ __('Name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Your full name">
            @if ($errors->has('name'))
                <div class="auth-error">{{ $errors->first('name') }}</div>
            @endif
        </div>

        <div class="auth-field">
            <label for="email">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@example.com">
            @if ($errors->has('email'))
                <div class="auth-error">{{ $errors->first('email') }}</div>
            @endif
        </div>

        <div class="auth-field">
            <label for="password">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Create a password">
            @if ($errors->has('password'))
                <div class="auth-error">{{ $errors->first('password') }}</div>
            @endif
        </div>

        <div class="auth-field">
            <label for="password_confirmation">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your password">
            @if ($errors->has('password_confirmation'))
                <div class="auth-error">{{ $errors->first('password_confirmation') }}</div>
            @endif
        </div>

        <button class="auth-submit" type="submit">{{ __('Create account') }} <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-description" style="margin: 18px 0 0; text-align: center">{{ __('Already registered?') }} <a class="auth-link" href="{{ route('login') }}">{{ __('Sign in') }}</a></p>
</x-guest-layout>
