@props([
    'title' => 'SmartExam',
    'subtitle' => 'SmartExam — Sistem Computer Based Test untuk Sekolah',
    'icon' => 'description',
    'toc' => [],
])

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
        html { scroll-behavior: smooth; }
        /* Tangani tinggi sticky header saat anchor jump */
        section[id] { scroll-margin-top: 5rem; }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface font-body-md text-on-surface min-h-screen flex flex-col overflow-x-hidden"
    x-data="{ scrolled: false }" @scroll.window="scrolled = window.scrollY > 8">

    {{-- Header sticky dengan logo + tombol kembali --}}
    <header class="sticky top-0 z-30 border-b border-outline-variant transition-shadow"
        :class="scrolled ? 'shadow-sm' : ''"
        style="background: linear-gradient(180deg, rgb(var(--color-surface-container)) 0%, rgb(var(--color-surface) / 0.85) 100%); backdrop-filter: blur(8px);">
        <div class="w-full max-w-container-max mx-auto px-lg md:px-xl py-2.5 flex items-center justify-between gap-md">

            {{-- Logo + identitas (tanpa border) --}}
            <a href="{{ route('login') }}" class="group flex items-center gap-sm">
                <img alt="SmartExam Logo" class="w-11 h-11 object-contain transition-transform group-hover:scale-105"
                    style="filter: drop-shadow(0 2px 4px rgb(var(--color-primary) / 0.35));"
                    src="{{ asset('images/logo1.png') }}">
                <span class="flex flex-col leading-tight">
                    <span
                        class="font-title-md text-title-md tracking-tight bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent group-hover:opacity-90 transition-opacity">
                        SmartExam
                    </span>
                    <span class="font-label-sm text-label-sm text-on-surface-variant">Computer Based Test</span>
                </span>
            </a>

            {{-- Aksi (satu tombol kembali) --}}
            <div class="flex items-center gap-sm">
                <a href="{{ route('login') }}"
                    class="inline-flex items-center gap-sm rounded-lg bg-primary hover:bg-primary/90 text-on-primary px-4 h-11 font-label-md text-label-md transition-all shadow-sm hover:shadow-md"
                    style="box-shadow: 0 2px 8px -2px rgb(var(--color-primary) / 0.4);">
                    <span class="material-symbols-outlined text-[18px]">login</span>
                    Kembali ke Login
                </a>
                <x-theme-toggle />
            </div>
        </div>
    </header>

    {{-- Breadcrumb --}}
    <nav class="w-full max-w-container-max mx-auto px-lg md:px-xl pt-lg md:pt-xl" aria-label="Breadcrumb">
        <ol class="flex items-center gap-sm font-label-md text-label-md text-on-surface-variant">
            <li>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-xs hover:text-primary transition-colors">
                    <span class="material-symbols-outlined text-[16px]">home</span>
                    Beranda
                </a>
            </li>
            <li class="text-outline" aria-hidden="true">/</li>
            <li class="text-on-surface font-medium" aria-current="page">{{ $title }}</li>
        </ol>
    </nav>

    {{-- Badan halaman: sidebar TOC (lg) + konten --}}
    <main class="flex-1 w-full max-w-container-max mx-auto px-lg md:px-xl py-lg md:py-xl">
        <div class="grid grid-cols-1 lg:grid-cols-[15rem_1fr] gap-lg lg:gap-xl items-start">

            {{-- Daftar Isi (sidebar desktop) --}}
            <aside class="hidden lg:block sticky top-24">
                <div class="bg-surface-container-low border border-outline-variant rounded-2xl p-md">
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-md px-sm">
                        Daftar Isi</p>
                    <ul class="space-y-xs">
                        @foreach ($toc as $section)
                            <li>
                                <a href="#{{ $section['id'] }}"
                                    class="flex items-center gap-sm rounded-lg px-sm py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-highest hover:text-primary transition-all">
                                    <span class="material-symbols-outlined text-[18px]">{{ $section['icon'] }}</span>
                                    {{ $section['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-md bg-primary-container/20 border border-primary/20 rounded-2xl p-md">
                    <p class="font-label-md text-label-md text-on-surface font-medium flex items-center gap-sm">
                        <span class="material-symbols-outlined text-[18px] text-primary">support_agent</span>
                        Butuh bantuan?
                    </p>
                    <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Hubungi admin sekolah atau
                        pengawas ruangan untuk pertanyaan terkait penggunaan SmartExam.</p>
                </div>
            </aside>

            {{-- Konten utama --}}
            <div class="w-full max-w-3xl mx-auto lg:mx-0 space-y-lg">

                {{-- Hero header halaman --}}
                <header class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-sm overflow-hidden">
                    <div class="relative p-lg md:p-xl">
                        <div class="absolute inset-0 pointer-events-none"
                            style="background: radial-gradient(circle at 100% 0%, rgb(var(--color-primary) / 0.08), transparent 55%);"></div>
                        <div class="relative flex items-start gap-md">
                            <span
                                class="hidden sm:inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary text-on-primary shrink-0">
                                <span class="material-symbols-outlined">{{ $icon }}</span>
                            </span>
                            <div>
                                <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-primary tracking-tight">
                                    {{ $title }}</h1>
                                <p class="font-body-md text-body-md text-on-surface-variant mt-xs">{{ $subtitle }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Daftar Isi ringkas (mobile): kotak di bawah header --}}
                    @if (count($toc))
                        <div class="lg:hidden border-t border-outline-variant bg-surface-container-low p-md">
                            <ul class="flex flex-wrap gap-sm">
                                @foreach ($toc as $section)
                                    <li>
                                        <a href="#{{ $section['id'] }}"
                                            class="inline-flex items-center gap-xs rounded-full border border-outline-variant bg-surface-container-lowest px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant hover:text-primary hover:border-primary transition-all">
                                            <span class="material-symbols-outlined text-[15px]">{{ $section['icon'] }}</span>
                                            {{ $section['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </header>

                {{-- Isi konten --}}
                <div class="space-y-lg text-on-surface">
                    {{ $slot }}
                </div>

                {{-- Footer konten --}}
                <footer class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg md:p-xl">
                    <p class="font-label-sm text-label-sm text-on-surface-variant flex items-center gap-sm">
                        <span class="material-symbols-outlined text-[16px]">update</span>
                        Terakhir diperbarui: {{ now()->translatedFormat('d F Y') }}
                    </p>
                </footer>
            </div>
        </div>
    </main>

    <x-footer />

    @stack('scripts')
</body>
</html>
