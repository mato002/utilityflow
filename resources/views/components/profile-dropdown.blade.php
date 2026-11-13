<details class="profile-dropdown" data-profile-dropdown>
    <summary class="topbar-avatar" aria-label="Open profile menu" title="{{ auth()->user()->name }}">
        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
    </summary>
    <div class="profile-dropdown-panel">
        <div class="profile-dropdown-identity">
            <span class="profile-dropdown-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span>
        </div>
        <a class="profile-dropdown-link" href="{{ route('profile.edit') }}"><span>♙</span>My profile</a>
        @if(auth()->user()->is_platform_admin)
            <a class="profile-dropdown-link" href="{{ route('platform.dashboard') }}"><span>◫</span>Platform dashboard</a>
        @elseif(auth()->user()->tenantAccounts()->exists())
            <a class="profile-dropdown-link" href="{{ route('tenant.dashboard') }}"><span>⌂</span>Resident portal</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="profile-dropdown-signout">
            @csrf
            <button type="submit"><span>↗</span>Sign out</button>
        </form>
    </div>
</details>
