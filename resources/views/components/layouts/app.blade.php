<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ $title ?? 'Scan' }}</title>
    @livewireStyles
    @filamentStyles
</head>
<body class="bg-gray-100 dark:bg-gray-950 min-h-screen antialiased">
    {{ $slot }}
    @livewireScripts
    @filamentScripts
</body>
</html>
