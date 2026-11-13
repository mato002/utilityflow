<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#155e57">
    <meta name="description" content="Record water meter readings by property and unit, with automatic consumption calculations.">
    <link rel="manifest" href="{{ asset('water-reader.webmanifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('water-icon.svg') }}">
    <title>Water Reader · UtilityFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="water-app-body">
    @yield('content')
</body>
</html>
