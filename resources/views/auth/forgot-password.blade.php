@extends('layouts.auth')

@section('content')
<span class="eyebrow">ACCOUNT RECOVERY</span>
<h2>Reset your password</h2>
<p class="form-intro">We’ll email a secure, time-limited reset link if this address has an account.</p>
@if(session('status'))<div class="flash flash-success" role="status">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" class="form-stack" data-auth-form novalidate>@csrf
    <label class="field-label" for="email">Account email</label>
    <input class="field-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
    @error('email')<p class="field-error">{{ $message }}</p>@enderror
    <button class="button button-primary button-wide" type="submit"><span data-submit-label>Send reset link</span><span class="submit-spinner" aria-hidden="true"></span></button>
</form>
<p class="auth-switch"><a href="{{ route('login') }}">Return to sign in</a></p>
@endsection
