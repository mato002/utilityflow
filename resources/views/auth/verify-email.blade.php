@extends('layouts.auth')

@section('content')
<span class="eyebrow">VERIFY YOUR EMAIL</span>
<h2>Check your inbox</h2>
<p class="form-intro">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Verify it to access your workspace securely.</p>
@if(session('status') === 'verification-link-sent')<div class="flash flash-success" role="status">A fresh verification link has been sent.</div>@endif
@if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('verification.send') }}" class="form-stack">@csrf<button class="button button-primary button-wide" type="submit">Resend verification email</button></form>
<form method="POST" action="{{ route('logout') }}" class="auth-switch">@csrf<button class="row-action" type="submit">Sign out</button></form>
@endsection
