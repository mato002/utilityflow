@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-story">
        <a class="brand brand-light" href="{{ route('login') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a>
        <div class="story-copy">
            <span class="eyebrow eyebrow-light">PROPERTY OPERATIONS, IN SYNC</span>
            <h1>Clarity for every<br>drop and every<br>kilowatt.</h1>
            <p>One calm workspace for the properties, people and utilities you manage.</p>
            <div class="story-features" aria-label="UtilityFlow features"><span>Meter readings</span><span>Billing</span><span>Tenant access</span></div>
        </div>
        <div class="story-foot"><span class="story-dot"></span> Secure workspace sign-in</div>
        <div class="story-orbit orbit-one"></div><div class="story-orbit orbit-two"></div>
    </section>
    <section class="auth-panel">
        <div class="auth-mobile-brand"><a class="brand" href="{{ route('login') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a></div>
        <div class="auth-card">@yield('content')</div>
        <p class="auth-legal">By continuing, you agree to UtilityFlow's <a href="#">Terms</a> and <a href="#">Privacy Policy</a>.</p>
    </section>
</main>
@endsection
