@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - {{ config('app.name', 'SmartExam') }}</title>
    @include('layouts.partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-800 dark:text-gray-200">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden bg-gray-100 dark:bg-gray-950">
        <x-sidebar :role="'admin'" />

        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 md:hidden"></div>

        <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
            <x-navbar :title="$title" />
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
