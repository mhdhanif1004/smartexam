<x-guest-layout title="Login" :full="true">
    <main class="relative min-h-screen flex flex-col lg:flex-row overflow-hidden bg-surface">
        {{-- Mesh-gradient full-screen (token M3 SmartExam) --}}
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true" style="
            background:
                radial-gradient(ellipse at 12% 15%, rgb(var(--color-primary-container) / 0.55), transparent 42%),
                radial-gradient(ellipse at 90% 85%, rgb(var(--color-secondary-container) / 0.55), transparent 42%),
                radial-gradient(ellipse at 70% 20%, rgb(var(--color-primary) / 0.28), transparent 45%),
                linear-gradient(160deg, rgb(var(--color-surface-container-lowest)), rgb(var(--color-surface-container)));
        "></div>

        {{-- Pola titik SVG ringan --}}
        <svg class="absolute inset-0 w-full h-full pointer-events-none opacity-30" aria-hidden="true">
            <defs>
                <pattern id="glass-dots" width="28" height="28" patternUnits="userSpaceOnUse">
                    <circle cx="2" cy="2" r="1.5" fill="rgb(var(--color-primary) / 0.2)" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#glass-dots)" />
        </svg>

        {{-- Panel Kiri: Branding + tagline (desktop lg+) --}}
        <section class="relative z-10 hidden lg:flex lg:w-1/2 flex-col items-center justify-center px-xl py-xl"
            aria-hidden="true">
            <div class="max-w-md text-center">
                <img alt="SmartExam Logo" class="w-32 h-32 mx-auto mb-md object-contain drop-shadow-lg"
                    src="{{ asset('images/logo1.png') }}">
                <h1 class="font-headline-lg text-headline-lg text-primary tracking-tight mb-sm">Selamat Datang di
                    SmartExam</h1>
                <p class="font-body-lg text-body-lg text-on-surface">Platform Computer Based Test (CBT) yang aman,
                    terpercaya, dan efisien untuk administrasi, pengawas, guru, dan peserta.</p>
            </div>
        </section>

        {{-- Panel Kanan: Kartu Kaca Form --}}
        <section class="relative z-10 flex-1 flex flex-col items-center justify-center px-lg py-xl min-h-screen lg:min-h-0">
            <div class="w-full max-w-md space-y-lg">
                {{-- Branding (mobile) --}}
                <div class="flex flex-col items-center text-center lg:hidden">
                    <img alt="SmartExam Logo" class="w-16 h-16 mb-sm object-contain drop-shadow"
                        src="{{ asset('images/logo1.png') }}">
                    <h1 class="font-headline-lg-mobile text-headline-lg-mobile text-primary tracking-tight">SmartExam</h1>
                    <p class="font-body-md text-body-md text-on-surface-variant">Sistem Computer Based Test Berbasis
                        Website</p>
                </div>

                {{-- Kartu kaca --}}
                <div
                    class="w-full bg-surface-container-lowest/70 backdrop-blur-xl border border-white/20 dark:border-white/10 rounded-2xl shadow-xl p-lg md:p-xl">
                    <!-- Session Status -->
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    @if (session('error'))
                        <div
                            class="mb-4 flex items-start gap-2 rounded-lg border border-error bg-error/15 px-4 py-3 text-sm text-error dark:text-on-error">
                            <span class="material-symbols-outlined mt-0.5 text-[16px]">error</span>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-md text-left" novalidate>
                        @csrf

                        <!-- Email Address (Username/ID Pengguna) -->
                        <div>
                            <label for="email"
                                class="block font-label-md text-label-md text-on-surface-variant mb-xs ml-1">Username/ID
                                Pengguna</label>
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline pointer-events-none">person</span>
                                <input id="email" name="email" type="text" value="{{ old('email') }}" required
                                    autofocus autocomplete="username" placeholder="Masukkan ID anda"
                                    class="w-full h-12 pl-11 pr-4 py-3 bg-surface/80 rounded-lg border focus:ring-1 transition-all font-body-md text-body-md {{ $errors->has('email') ? 'border-error focus:border-error focus:ring-error' : 'border-outline-variant focus:border-primary focus:ring-primary' }}">
                            </div>
                            @error('email')
                                <p class="mt-2 flex items-center gap-xs text-label-md text-error" role="alert">
                                    <span class="material-symbols-outlined text-[16px]">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password"
                                class="block font-label-md text-label-md text-on-surface-variant mb-xs ml-1">Password</label>
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline pointer-events-none">lock</span>
                                <input id="password" name="password" type="password" required
                                    autocomplete="current-password" placeholder="••••••••"
                                    class="w-full h-12 pl-11 pr-12 py-3 bg-surface/80 rounded-lg border focus:ring-1 transition-all font-body-md text-body-md {{ $errors->has('password') ? 'border-error focus:border-error focus:ring-error' : 'border-outline-variant focus:border-primary focus:ring-primary' }}">
                                <x-password-toggle position="absolute right-3 top-1/2 -translate-y-1/2"
                                    color="text-outline hover:text-primary" />
                            </div>
                            @error('password')
                                <p class="mt-2 flex items-center gap-xs text-label-md text-error" role="alert">
                                    <span class="material-symbols-outlined text-[16px]">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Remember Me -->
                        <div class="pt-xs">
                            <label for="remember_me"
                                class="flex items-center gap-xs cursor-pointer font-label-md text-label-md text-on-surface-variant select-none">
                                <input id="remember_me" type="checkbox" name="remember" checked
                                    class="h-4 w-4 rounded bg-surface border-outline-variant text-primary focus:ring-primary focus:ring-1">
                                <span>Ingat Saya</span>
                            </label>
                        </div>

                        <!-- Submit -->
                        <div class="pt-sm">
                            <button type="submit"
                                class="w-full h-12 bg-primary hover:bg-primary/90 text-on-primary px-4 rounded-lg font-title-md text-title-md shadow-lg shadow-primary/25 transition-all active:scale-[0.98] flex items-center justify-center gap-sm">
                                Masuk
                                <span class="material-symbols-outlined text-[20px]">login</span>
                            </button>
                        </div>
                    </form>
                    <br>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
