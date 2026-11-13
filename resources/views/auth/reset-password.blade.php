@extends('layouts.auth')

@section('content')
<span class="eyebrow">SECURE ACCOUNT ACCESS</span>
<h2>Choose a new password</h2>
<p class="form-intro">Use at least 12 characters with uppercase, lowercase, numbers and a symbol.</p>
<form method="POST" action="{{ route('password.update') }}" class="form-stack" data-auth-form novalidate>@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label class="field-label" for="email">Account email</label>
    <input class="field-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>
    @error('email')<p class="field-error">{{ $message }}</p>@enderror
    <label class="field-label" for="password">New password</label>
    <div class="auth-password-wrap"><input class="field-input" id="password" name="password" type="password" autocomplete="new-password" required><button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div>
    @error('password')<p class="field-error">{{ $message }}</p>@enderror
    <label class="field-label" for="password_confirmation">Confirm password</label>
    <div class="auth-password-wrap"><input class="field-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div>
    <button class="button button-primary button-wide" type="submit"><span data-submit-label>Save new password</span><span class="submit-spinner" aria-hidden="true"></span></button>
</form>
<p class="auth-switch"><a href="{{ route('login') }}">Return to sign in</a></p>
@endsection
