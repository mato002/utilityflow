@extends('layouts.app')
@php($title = 'My profile')

@section('body')
<main class="account-shell">
    <header class="account-topbar">
        <a class="brand" href="{{ auth()->user()->is_platform_admin ? route('platform.dashboard') : (auth()->user()->tenantAccounts()->exists() ? route('tenant.dashboard') : route('dashboard')) }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-muted" type="submit">Sign out</button></form>
    </header>
    <div class="account-content">
        <a class="account-back-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}">← Back</a>
        <div class="account-heading"><span class="profile-dropdown-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><p class="eyebrow">ACCOUNT</p><h1>My profile</h1><p>Manage your personal details and password.</p></div></div>
        @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
        @if(session('password-success'))<div class="flash flash-success" role="status">{{ session('password-success') }}</div>@endif
        @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
        <section class="panel account-card"><div class="panel-heading"><div><h2>Personal details</h2><p>Your name appears across your UtilityFlow account.</p></div></div><form method="POST" action="{{ route('profile.update') }}" class="account-form">@csrf @method('PUT')<label class="account-field"><span>Full name</span><input class="field-input" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="120" autocomplete="name"></label><label class="account-field"><span>Email address</span><input class="field-input" value="{{ auth()->user()->email }}" disabled><small>Email changes require verification. Contact support if you need to change it.</small></label><div class="account-actions"><button class="button button-primary" type="submit">Save profile</button></div></form></section>
        <section class="panel account-card"><div class="panel-heading"><div><h2>Change password</h2><p>12+ characters, mixed case, number and symbol.</p></div></div><form method="POST" action="{{ route('profile.password.update') }}" class="account-form">@csrf @method('PUT')<label class="account-field"><span>Current password</span><input class="field-input" type="password" name="current_password" required autocomplete="current-password"></label><label class="account-field"><span>New password</span><input class="field-input" type="password" name="password" required autocomplete="new-password"></label><label class="account-field"><span>Confirm new password</span><input class="field-input" type="password" name="password_confirmation" required autocomplete="new-password"></label><div class="account-actions"><button class="button button-primary" type="submit">Update password</button></div></form></section>
    </div>
</main>
@endsection
