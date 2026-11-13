<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#21786f">
    <meta name="description" content="@yield('meta_description', 'UtilityFlow helps property teams manage buildings, tenants, utility meters, billing and maintenance in one workspace.')">
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:site_name" content="UtilityFlow">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', ($title ?? 'Workspace').' · UtilityFlow')">
    <meta property="og:description" content="@yield('meta_description', 'UtilityFlow helps property teams manage buildings, tenants, utility meters, billing and maintenance in one workspace.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta name="twitter:card" content="summary">
    @yield('structured_data')
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg').'?v=2' }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico').'?v=2' }}" sizes="32x32">
    <title>@yield('meta_title', ($title ?? 'Workspace').' · UtilityFlow')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @yield('body')
</body>
</html>
