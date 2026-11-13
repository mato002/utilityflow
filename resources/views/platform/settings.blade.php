<div>
    <!-- Always remember that you are absolutely unique. Just like everyone else. - Margaret Mead -->
</div>
@extends('layouts.app')
@php($title = 'Platform settings')

@section('body')
<div class="app-shell platform-admin-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('platform.dashboard') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a>
        <div class="platform-tag"><span></span> PLATFORM ADMIN</div>
        <nav class="side-nav" aria-label="Platform navigation">
            <span class="nav-caption">OVERVIEW</span><a class="nav-item" href="{{ route('platform.dashboard') }}"><span class="nav-icon">◫</span><span>Platform overview</span></a>
            <span class="nav-caption nav-caption-lower">PLATFORM</span><a class="nav-item" href="{{ route('platform.organizations') }}"><span class="nav-icon">♧</span><span>Subscribers &amp; subscriptions</span></a><a class="nav-item active" href="{{ route('platform.settings') }}"><span class="nav-icon">⚙</span><span>Settings &amp; audit</span></a>
        </nav>
        <div class="sidebar-bottom"><div class="user-menu"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="user-details">{{ auth()->user()->name }}<small>Platform administrator</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" aria-label="Sign out">↗</button></form></div></div>
    </aside>
    <main class="main-content">
        <header class="topbar"><div class="breadcrumbs"><a href="{{ route('platform.dashboard') }}">Platform</a><span class="crumb-slash">/</span><strong>Settings &amp; audit</strong></div><div class="topbar-right"><span class="live-status"><span></span>Platform online</span>@include('components.profile-dropdown')</div></header>
        <nav class="workspace-subnav platform-subnav" aria-label="Settings sections"><span class="workspace-subnav-label">Configure</span><div class="workspace-subnav-items" role="tablist"><a class="workspace-subnav-link active" href="#platform-settings" role="tab" aria-selected="true" aria-controls="platform-settings" data-platform-tab="platform-settings">Platform settings</a><a class="workspace-subnav-link" href="#audit-log" role="tab" aria-selected="false" aria-controls="audit-log" data-platform-tab="audit-log">Audit log</a></div></nav>
        <div class="page-wrap platform-admin-page-wrap">
            @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
            <div class="module-heading-row"><div class="module-title-wrap"><span class="module-icon">⚙</span><div><p class="eyebrow">PLATFORM ADMINISTRATION</p><h1>Settings &amp; audit</h1><p class="page-subtitle">Control workspace onboarding defaults and review administrator activity.</p></div></div></div>
            <section class="panel org-table-panel platform-admin-section platform-settings-panel" id="platform-settings" data-platform-panel>
                <div class="panel-heading"><div><h3>Workspace defaults</h3><p>These options apply when a new organization joins the platform.</p></div></div>
                <form method="POST" action="{{ route('platform.settings.update') }}" class="platform-settings-form">@csrf @method('PUT')
                    <label class="platform-setting-toggle"><span><strong>Allow new workspace registrations</strong><small>When disabled, the public registration page is paused. Existing users can still sign in.</small></span><input type="checkbox" name="registration_open" value="1" @checked(old('registration_open', $registrationOpen))></label>
                    <label class="dialog-field platform-timezone-field"><span>Default timezone for new workspaces</span><select name="default_timezone" required>@foreach($timezoneOptions as $timezone)<option value="{{ $timezone }}" @selected(old('default_timezone', $defaultTimezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></label>
                    @error('default_timezone')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <div class="account-actions"><button class="button button-primary" type="submit">Save platform settings</button></div>
                </form>
            </section>
            <section class="panel org-table-panel platform-admin-section" id="audit-log" data-platform-panel hidden>
                <div class="panel-heading"><div><h3>Administrator activity</h3><p>Recent changes recorded across workspace and platform management.</p></div><span class="table-count">{{ $auditLogs->total() }} events</span></div>
                <div class="table-scroll"><table class="data-table platform-org-table"><thead><tr><th>ACTIVITY</th><th>ADMINISTRATOR</th><th>WORKSPACE</th><th>SUBJECT</th><th>IP ADDRESS</th><th>TIME</th></tr></thead><tbody>
                    @forelse($auditLogs as $log)<tr><td><span class="platform-audit-action">{{ str($log->action)->replace('.', ' · ')->replace('_', ' ')->title() }}</span></td><td>{{ $log->actor?->name ?? 'System' }}</td><td>{{ $log->organization?->name ?? 'Platform' }}</td><td>{{ $log->subject_type ? class_basename($log->subject_type) : '—' }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</td><td>{{ $log->ip_address ?? '—' }}</td><td>{{ $log->created_at->format('j M Y, H:i') }}</td></tr>@empty<tr><td colspan="6" class="table-empty">No audit events have been recorded yet.</td></tr>@endforelse
                </tbody></table></div><div class="records-footer"><span>Latest administrator activity</span>{{ $auditLogs->links('pagination::simple-tailwind') }}</div>
            </section>
            <footer class="dashboard-footer"><span>© {{ now()->year }} UtilityFlow · Platform administration</span><span>Settings changes are written to the audit log</span><a href="mailto:support@utilityflow.app">Help &amp; support</a></footer>
        </div>
    </main>
</div>
@endsection
