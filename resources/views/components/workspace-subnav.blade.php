<nav class="workspace-subnav" aria-label="{{ $navigationGroup['label'] }} sections">
    <span class="workspace-subnav-label">{{ $navigationGroup['label'] }}</span>
    <div class="workspace-subnav-items">
        @foreach($navigationGroup['items'] as $item)
            <a class="workspace-subnav-link {{ $currentModule === $item['key'] ? 'active' : '' }}" href="{{ $item['url'] }}" @if($currentModule === $item['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>
