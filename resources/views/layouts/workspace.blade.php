@extends('layouts.app')

@section('body')
@php($selectedPalette = config('brand_palettes.'.(auth()->user()->color_palette ?? $organization->color_palette ?? 'utility-green'), config('brand_palettes.utility-green')))
<div class="app-shell" style="--brand-primary: {{ $selectedPalette['primary'] }}; --brand-strong: {{ $selectedPalette['strong'] }}; --brand-soft: {{ $selectedPalette['soft'] }}; --brand-light: {{ $selectedPalette['light'] }}; --brand-sidebar: {{ $selectedPalette['strong'] }}; --brand-header: {{ $selectedPalette['light'] }}; --brand-canvas: {{ $selectedPalette['canvas'] }}; --brand-border: {{ $selectedPalette['border'] }}">
    @include('components.workspace-sidebar', ['workspaceNavigation' => $workspaceNavigation, 'activeNavigationGroup' => $activeNavigationGroup])
    <main class="main-content">
        @include('components.workspace-topbar')
        @if($activeNavigationGroup)
            @include('components.workspace-subnav', ['navigationGroup' => $workspaceNavigation[$activeNavigationGroup]])
        @endif
        <div class="page-wrap">@yield('workspace-content')</div>
    </main>
</div>
@endsection
