@extends('layouts.app')
@php($title = 'UtilityFlow property and utility management')

@section('meta_title', 'UtilityFlow | Property, tenant and utility management')
@section('meta_description', 'Manage properties, tenants, utility meters, readings, billing, payments and maintenance from one clear property operations workspace.')
@section('robots', 'index, follow, max-image-preview:large')
@section('canonical', route('home'))
@section('og_title', 'UtilityFlow | Property and utility operations in one workspace')
@section('og_type', 'website')
@section('structured_data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'UtilityFlow',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'description' => 'Property operations software for managing buildings, tenants, utility usage, billing, payments and maintenance.',
        'url' => route('home'),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('body')
<main class="marketing-page">
    <header class="marketing-header">
        <a class="brand" href="{{ route('home') }}" aria-label="UtilityFlow home"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a>
        <nav aria-label="Main navigation"><a href="#features">Features</a><a href="#how-it-works">How it works</a><a class="button button-muted" href="{{ route('login') }}">Sign in</a><a class="button button-primary" href="{{ route('register') }}">Create workspace</a></nav>
    </header>
    <section class="marketing-hero">
        <div class="marketing-hero-copy"><p class="eyebrow">PROPERTY OPERATIONS, IN SYNC</p><h1>One clear place to manage your properties and utilities.</h1><p>UtilityFlow brings buildings, tenants, meter readings, billing, payments and maintenance into one organized workspace for property teams.</p><div class="marketing-hero-actions"><a class="button button-primary" href="{{ route('register') }}">Get started <span aria-hidden="true">→</span></a><a class="marketing-text-link" href="#features">Explore the features</a></div><ul class="marketing-highlights"><li>Property and unit records</li><li>Utility reading review</li><li>Invoices and payment tracking</li></ul></div>
        <div class="marketing-hero-card" aria-label="UtilityFlow operations overview"><div class="marketing-card-top"><span><i></i> WORKSPACE OVERVIEW</span><b>UtilityFlow</b></div><div class="marketing-card-heading"><span>Portfolio health</span><strong>Operations, connected.</strong></div><div class="marketing-card-grid"><article><span>PROPERTIES</span><b>Buildings</b><small>Portfolio records</small></article><article><span>UTILITIES</span><b>Water &amp; power</b><small>Metered usage</small></article><article><span>BILLING</span><b>Invoices</b><small>Balances and receipts</small></article><article><span>OPERATIONS</span><b>Maintenance</b><small>Requests and follow-up</small></article></div><div class="marketing-card-footer"><span>One workspace for your team</span><span class="marketing-status">● Organized</span></div></div>
    </section>
    <section class="marketing-section" id="features"><p class="eyebrow">A PRACTICAL OPERATIONS TOOLKIT</p><h2>Keep property work moving from one system.</h2><p class="marketing-section-intro">Bring day-to-day records together so your team can follow usage, tenant accounts and property tasks with less manual coordination.</p><div class="marketing-feature-grid"><article><span>01</span><h3>Manage properties and tenants</h3><p>Organize properties, buildings, floors, units and tenant records in a shared workspace.</p></article><article><span>02</span><h3>Track utility readings</h3><p>Capture meter readings, review submissions and keep consumption history connected to the right account.</p></article><article><span>03</span><h3>Prepare and follow billing</h3><p>Build billing cycles, issue invoices, record receipts and review outstanding balances.</p></article><article><span>04</span><h3>Coordinate maintenance</h3><p>Record maintenance requests and keep team updates and property operations in view.</p></article></div></section>
    <section class="marketing-bottom" id="how-it-works"><div><p class="eyebrow">BUILT FOR PROPERTY TEAMS</p><h2>Make the next property task easier to find.</h2><p>Set up a workspace, add your properties and units, then invite your team to manage utilities and tenant operations together.</p></div><a class="button button-primary" href="{{ route('register') }}">Create your workspace <span aria-hidden="true">→</span></a></section>
    <footer class="marketing-footer"><a class="brand" href="{{ route('home') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a><span>Property operations in one workspace.</span><a href="mailto:support@utilityflow.app">Contact support</a><span>© {{ now()->year }} UtilityFlow</span></footer>
</main>
@endsection
