@extends('layouts.app')
@php($title = 'Resident portal invitation')

@section('body')
<main class="invitation-shell"><section class="panel invitation-card"><a class="brand" href="{{ route('login') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a><p class="eyebrow">RESIDENT PORTAL</p><h1>You’re invited</h1><p>{{ $invitation->tenant->organization->name }} invited <strong>{{ $invitation->email }}</strong> to view their utility account.</p>
    @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(auth()->check())
        <form method="POST" action="{{ route('tenant.invitations.store', ['token' => request()->route('token')]) }}">@csrf<button class="button button-primary button-wide" type="submit">Activate my resident portal</button></form>
    @elseif($accountExists)
        <p>This email already has a UtilityFlow account. Sign in with it to connect the resident portal.</p><a class="button button-primary button-wide" href="{{ route('login') }}">Sign in to accept</a>
    @else
        <form method="POST" action="{{ route('tenant.invitations.store', ['token' => request()->route('token')]) }}" class="form-stack">@csrf<label class="field-label" for="name">Your name</label><input class="field-input" id="name" name="name" value="{{ old('name', $invitation->tenant->name) }}" required maxlength="120" autocomplete="name"><label class="field-label" for="password">Create a password</label><input class="field-input" id="password" name="password" type="password" required autocomplete="new-password"><label class="field-label" for="password_confirmation">Confirm password</label><input class="field-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">@if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif<button class="button button-primary button-wide" type="submit">Create account &amp; activate portal</button></form>
    @endif
    <p class="invitation-expiry">This secure invitation expires {{ $invitation->expires_at->timezone($invitation->tenant->organization->timezone)->format('j M Y, H:i') }}.</p>
</section></main>
@endsection
