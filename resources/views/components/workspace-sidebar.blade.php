<aside class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a>
    <button class="workspace-select" type="button" data-menu-toggle="workspace-options" aria-expanded="false">
        <span class="workspace-icon">{{ strtoupper(substr($organization->name, 0, 1)) }}</span>
        <span class="workspace-name">{{ $organization->name }}<small>{{ $roleName ?? 'Workspace' }}</small></span>
        <span class="workspace-caret">⌄</span>
    </button>
    @if($organizations->count() > 1)
        <div class="workspace-options" hidden>
            @foreach($organizations as $item)
                @if($item->id !== $organization->id)
                    <form method="POST" action="{{ route('workspace.switch', $item) }}">@csrf<button class="workspace-option">Switch to {{ $item->name }}</button></form>
                @endif
            @endforeach
        </div>
    @endif
    <nav class="side-nav" aria-label="Main navigation">
        <span class="nav-caption">WORKSPACE</span>
        <a class="nav-item {{ $currentModule === 'overview' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">◫</span><span>Overview</span></a>
        @foreach($workspaceNavigation as $groupKey => $group)
            <a class="nav-item {{ $activeNavigationGroup === $groupKey ? 'active' : '' }}" href="{{ $group['items'][0]['url'] }}"><span class="nav-icon">{{ $group['icon'] }}</span><span>{{ $group['label'] }}</span></a>
        @endforeach
    </nav>
    <div class="sidebar-bottom">
        <div class="user-menu"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="user-details">{{ auth()->user()->name }}<small>{{ auth()->user()->email }}</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" aria-label="Sign out" title="Sign out">↗</button></form></div>
    </div>
</aside>
