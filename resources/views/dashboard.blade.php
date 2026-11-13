@extends('layouts.workspace')
@php($title = 'Overview')
@php($pageTitle = 'Overview')

@section('workspace-content')
    <div class="welcome-row">
        <div><p class="date-line">{{ now()->timezone($organization->timezone)->format('l, j F Y') }}</p><h1>Good {{ now()->format('H') < 12 ? 'morning' : (now()->format('H') < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }} <span class="wave">✦</span></h1></div>
        <a class="button button-muted" href="{{ route('workspace.module', 'reports') }}">⌑ <span>View reports</span>⌄</a>
    </div>
    <div class="section-heading"><h2>Overview</h2></div>
    <section class="metric-grid" aria-label="Organization summary">
        <a class="metric-card" href="{{ route('workspace.module', 'properties') }}"><div class="metric-top"><span class="metric-label">PROPERTIES</span><span class="metric-icon metric-blue">⌂</span></div><div class="metric-value">{{ number_format($propertyCount) }}</div><div class="metric-foot">In your property portfolio <span class="mini-link">→</span></div><div class="metric-spark spark-blue"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></a>
        <a class="metric-card" href="{{ route('workspace.module', 'spaces') }}"><div class="metric-top"><span class="metric-label">OCCUPIED UNITS</span><span class="metric-icon metric-green">▦</span></div><div class="metric-value">{{ number_format($occupiedUnitCount) }}</div><div class="metric-foot">Across your portfolio <span class="mini-link">↗</span></div><div class="metric-spark spark-green"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></a>
        <a class="metric-card" href="{{ route('workspace.module', 'meters') }}"><div class="metric-top"><span class="metric-label">ACTIVE METERS</span><span class="metric-icon metric-amber">⌁</span></div><div class="metric-value">{{ number_format($activeMeterCount) }}</div><div class="metric-foot">Water and electricity meters <span class="mini-link">↗</span></div><div class="metric-spark spark-amber"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></a>
        <a class="metric-card" href="{{ route('workspace.module', 'billing') }}"><div class="metric-top"><span class="metric-label">OUTSTANDING</span><span class="metric-icon metric-violet">▤</span></div><div class="metric-value metric-word">KES {{ number_format((float) $outstandingBalance, 2) }}</div><div class="metric-foot">Utility balances to collect <span class="mini-link">↗</span></div><div class="metric-spark spark-violet"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></a>
    </section>
    <section class="dashboard-panels">
        <article class="panel consumption-panel"><div class="panel-heading"><h3>Utility consumption</h3><a class="select-small" href="{{ route('workspace.module', 'readings') }}">Readings&nbsp; ↗</a></div><div class="chart-legend"><span><i class="legend-water"></i>Water</span><span><i class="legend-power"></i>Electricity</span></div><div class="chart-empty"><div class="chart-lines"><span>300</span><span>200</span><span>100</span><span>0</span></div><div class="chart-area"><div class="chart-gridlines"><i></i><i></i><i></i><i></i></div><svg viewBox="0 0 650 180" preserveAspectRatio="none" aria-label="No consumption readings yet"><path d="M0 145 C80 138 105 126 160 134 S250 115 315 125 S400 93 470 108 S570 74 650 89" fill="none" stroke="#b6d6d4" stroke-width="3" stroke-dasharray="5 7"/><path d="M0 164 C95 150 130 157 205 145 S310 157 380 133 S500 143 560 121 S620 132 650 113" fill="none" stroke="#d8c9a8" stroke-width="3" stroke-dasharray="5 7"/></svg><div class="axis-labels"><span>1 Nov</span><span>7 Nov</span><span>14 Nov</span><span>21 Nov</span><span>30 Nov</span></div></div></div><div class="chart-note">No readings yet</div></article>
        <article class="panel activity-panel"><div class="panel-heading"><h3>Recent activity</h3><a class="more-button" href="{{ route('workspace.module', 'notifications') }}" aria-label="View notifications">···</a></div><div class="activity-empty"><span class="empty-icon">◷</span><strong>No activity</strong></div></article>
    </section>
    <footer class="dashboard-footer"><span>© {{ now()->year }} UtilityFlow</span><a href="mailto:support@utilityflow.app">Support</a></footer>
@endsection
