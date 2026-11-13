@extends('layouts.workspace')
@php($title = 'Subscription')
@php($pageTitle = 'Subscription')

@section('workspace-content')
    @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="module-heading-row"><div class="module-title-wrap"><span class="module-icon">◈</span><div><p class="eyebrow">SAAS PLAN</p><h1>Subscription</h1></div></div></div>
    @if($subscription)<section class="subscription-hero panel"><div><span class="eyebrow">CURRENT PLAN</span><h2>{{ $subscription->plan->name }}</h2><p>{{ $subscription->plan->description }}</p><span class="status-pill {{ in_array($subscription->status, ['active', 'trial']) ? 'status-active' : 'status-paused' }}">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span></div><div class="subscription-price"><strong>{{ $subscription->plan->currency }} {{ number_format((float) ($subscription->billing_interval === 'annual' ? $subscription->plan->annual_price : $subscription->plan->monthly_price), 2) }}</strong><span>/ {{ $subscription->billing_interval }}</span>@if($subscription->trial_ends_at)<small>Trial ends {{ $subscription->trial_ends_at->timezone($organization->timezone)->format('j M Y') }}</small>@endif</div></section>
    <section class="panel org-table-panel subscription-usage"><div class="panel-heading"><div><span class="eyebrow">PLAN CAPACITY</span><h3>Workspace usage</h3></div></div>@foreach(['properties' => 'Properties', 'units' => 'Units', 'staff' => 'Staff'] as $key => $label)@php($limit = data_get($subscription->plan->limits, $key))<div class="usage-row"><span><strong>{{ $label }}</strong><small>{{ number_format($usage[$key]) }} used{{ $limit !== null ? ' of '.$limit : '' }}</small></span><div class="usage-bar"><i style="width: {{ $limit ? min(100, ($usage[$key] / max(1, $limit)) * 100) : 100 }}%"></i></div><b>{{ $limit === null ? '∞' : number_format(max(0, $limit - $usage[$key])) }}</b></div>@endforeach</section>
    @else<section class="panel subscription-empty"><span class="module-icon">◈</span><h2>No plan assigned</h2></section>@endif
    <section class="panel account-card subscription-choice-card"><div class="panel-heading"><div><span class="eyebrow">PLAN MANAGEMENT</span><h2>Choose or complete a subscription</h2></div></div>@include('workspace.partials.subscription-selector')</section>
    <footer class="dashboard-footer"><span>© {{ now()->year }} UtilityFlow</span><span>Paid checkout activates when the payment API is connected</span><a href="mailto:support@utilityflow.app">Contact support</a></footer>
@endsection
