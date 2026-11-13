@extends('layouts.app')

@section('body')
<main class="invitation-shell"><article class="panel invitation-card"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><p class="eyebrow">WORKSPACE INVITATION</p><h1>Join {{ $invitation->organization->name }}</h1><p>You’ll join as <strong>{{ $invitation->role->name }}</strong> using {{ auth()->user()->email }}.</p><form method="POST" action="{{ route('invitations.accept', request()->route('token')) }}">@csrf<button class="button button-primary button-wide" type="submit">Accept invitation <span>→</span></button></form></article></main>
@endsection
