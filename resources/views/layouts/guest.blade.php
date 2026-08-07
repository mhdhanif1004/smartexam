@props(['title' => 'SmartExam', 'full' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} - {{ config('app.name', 'SmartExam') }}</title>

    @include('layouts.partials.theme-init')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .focus-ring:focus {
            box-shadow: 0 0 0 2px white, 0 0 0 4px #00288e;
            outline: none;
        }
        .dark .focus-ring:focus {
            box-shadow: 0 0 0 2px #0d1526, 0 0 0 4px #b8c4ff;
        }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface font-body-md text-on-surface min-h-screen flex flex-col overflow-x-hidden">
    @if ($full)
        {{ $slot }}
    @else
        <main class="flex-grow flex items-center justify-center px-lg py-xl">
            <div class="w-full max-w-md bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm">
                {{ $slot }}
            </div>
        </main>
    @endif

    <x-footer />

    <div class="fixed right-4 bottom-4 z-50">
        <x-theme-toggle />
    </div>

    @stack('scripts')
</body>
</html>
