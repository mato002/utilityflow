@extends('layouts.auth')

@section('content')
<span class="eyebrow">YOUR OPERATIONS, ONE PLACE</span>
<h2>Welcome back</h2>
<p class="form-intro">Sign in to your UtilityFlow workspace.</p>
@if(session('status'))<div class="flash flash-success" role="status">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('login') }}" class="form-stack" data-auth-form novalidate>@csrf
    <label class="field-label" for="email">Work email</label>
    <input class="field-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="you@company.com">
    @error('email')<p class="field-error">{{ $message }}</p>@enderror
    <div class="password-label"><label class="field-label" for="password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
    <div class="auth-password-wrap"><input class="field-input" id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password"><button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div>
    @error('password')<p class="field-error">{{ $message }}</p>@enderror
    <label class="check-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
    <button class="button button-primary button-wide" type="submit"><span data-submit-label>Sign in</span><span class="submit-arrow" aria-hidden="true">→</span><span class="submit-spinner" aria-hidden="true"></span></button>
</form>
<p class="auth-switch">New to UtilityFlow? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
