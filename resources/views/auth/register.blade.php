@extends('layouts.auth')

@section('content')
<span class="eyebrow">GET YOUR WORKSPACE READY</span>
<h2>Start with your team</h2>
<p class="form-intro">Create your organization and owner account.</p>
<form method="POST" action="{{ route('register') }}" class="form-stack" data-auth-form novalidate>@csrf
    <label class="field-label" for="organization_name">Organization name</label>
    <input class="field-input" id="organization_name" name="organization_name" value="{{ old('organization_name') }}" required autocomplete="organization" placeholder="e.g. Acacia Property Group">
    @error('organization_name')<p class="field-error">{{ $message }}</p>@enderror
    <label class="field-label" for="name">Your full name</label>
    <input class="field-input" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="e.g. Amina Otieno">
    @error('name')<p class="field-error">{{ $message }}</p>@enderror
    <label class="field-label" for="email">Work email</label>
    <input class="field-input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@company.com">
    @error('email')<p class="field-error">{{ $message }}</p>@enderror
    <label class="field-label" for="password">Password</label>
    <div class="auth-password-wrap"><input class="field-input" id="password" name="password" type="password" required autocomplete="new-password" placeholder="At least 10 characters"><button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div>
    @error('password')<p class="field-error">{{ $message }}</p>@enderror
    <label class="field-label" for="password_confirmation">Confirm password</label>
    <div class="auth-password-wrap"><input class="field-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Enter it once more"><button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div>
    <button class="button button-primary button-wide" type="submit"><span data-submit-label>Create workspace</span><span class="submit-arrow" aria-hidden="true">→</span><span class="submit-spinner" aria-hidden="true"></span></button>
</form>
<p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
