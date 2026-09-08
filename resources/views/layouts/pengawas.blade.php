@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="fcm-enabled" content="1">
    <title>{{ $title }} - {{ config('app.name', 'SmartExam') }}</title>
    @include('layouts.partials.theme-init')
    {{-- Turbo Drive (navigasi instan antar menu tanpa reload). Matikan kapan saja via
         TURBO_ENABLED=false di .env => app.js tidak akan memuat Turbo (rollback cepat). --}}
    @if (config('app.turbo_enabled'))
        <meta name="turbo-cache-control" content="no-cache">
        <meta name="turbo-root" content="/">
        <script>window.SMARTEXAM_TURBO_ENABLED = true;</script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-800 dark:text-gray-200">
    <div x-data class="flex h-screen overflow-hidden bg-gray-100 dark:bg-gray-950">
        <x-sidebar :role="'pengawas'" />

        <div x-show="$store.sidebar.open" x-cloak @click="$store.sidebar.open = false" id="smartexam-sidebar-overlay-pengawas" data-turbo-permanent class="fixed inset-0 z-30 bg-gray-900/50 md:hidden"></div>

        <div class="flex min-w-0 flex-1 flex-col overscroll-contain overflow-y-auto">
            <x-navbar :title="$title" />
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    {{-- Background violation polling: Worker + suara + Notification API.
         TANPA panel visual — panel hanya ada di Dashboard. --}}
    <div x-data="violationPolling({
        endpoint: '{{ route('pengawas.violations.polling') }}',
        csrf: '{{ csrf_token() }}',
        csrfUrl: '{{ route('csrf-token') }}',
        userKey: '{{ auth()->user()->role . '-' . auth()->user()->id }}',
        handleUrl: null,
        initialViolations: [],
    })" x-init></div>
    @stack('scripts')
</body>
</html>
