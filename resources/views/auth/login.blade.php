<x-guest-layout title="Login" :full="true">
    <main class="min-h-screen flex flex-col md:flex-row">
        <!-- Panel Kiri: Gambar Ilustrasi (~48%) -->
        <section class="hidden md:block md:w-[48%] h-screen relative overflow-hidden" aria-hidden="true">
            <img src="{{ asset('images/login-illustration-baru.jpg') }}" alt="Ilustrasi siswa menuju sekolah SmartExam"
                class="w-full h-full object-cover object-center-top" loading="eager">
            <!-- Overlay transisi halus ke panel kanan (background surface) -->
            <div class="absolute inset-y-0 right-0 w-32 pointer-events-none bg-gradient-to-r from-transparent to-[rgb(var(--color-surface))]"
                aria-hidden="true"></div>
            <!-- Subtle separator line -->
            <div class="absolute right-0 top-0 bottom-0 w-px bg-gradient-to-b from-transparent via-outline/30 to-transparent"
                aria-hidden="true"></div>
        </section>

        <!-- Panel Kanan: Form Login (~52%) -->
        <section class="flex-1 flex flex-col items-center justify-center bg-surface p-lg md:p-xl">
            <div class="w-full max-w-md">
                <div class="mb-xl flex flex-col items-center text-center">
                    <img alt="SmartExam Logo" class="w-24 h-24 mb-md object-contain"
                        src="{{ asset('images/logo1.png') }}">
                    <h1 class="font-headline-lg text-headline-lg text-primary tracking-tight">SmartExam</h1>
                    <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Sistem Computer Based Test
                        Berbasis Website</p>
                </div>

                <div
                    class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm p-lg md:p-xl">
                    <!-- Session Status -->
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    @if (session('error'))
                        <div
                            class="mb-4 flex items-start gap-2 rounded-lg border border-error bg-error-container/20 px-4 py-3 text-sm text-error">
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
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">person</span>
                                <input id="email" name="email" type="text" value="{{ old('email') }}" required
                                    autofocus autocomplete="username" placeholder="Masukkan ID anda"
                                    class="w-full pl-10 pr-4 py-3 bg-surface rounded border focus:ring-1 transition-all font-body-md text-body-md {{ $errors->has('email') ? 'border-error focus:border-error focus:ring-error' : 'border-outline-variant focus:border-primary focus:ring-primary' }}">
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
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">lock</span>
                                <input id="password" name="password" type="password" required
                                    autocomplete="current-password" placeholder="••••••••"
                                    class="w-full pl-10 pr-10 py-3 bg-surface rounded border focus:ring-1 transition-all font-body-md text-body-md {{ $errors->has('password') ? 'border-error focus:border-error focus:ring-error' : 'border-outline-variant focus:border-primary focus:ring-primary' }}">
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
                                class="w-full bg-primary hover:bg-primary-container text-on-primary py-3 rounded-lg font-title-md text-title-md shadow-lg shadow-primary/10 transition-all active:scale-[0.98] flex items-center justify-center gap-sm">
                                Masuk
                                <span class="material-symbols-outlined text-[20px]">login</span>
                            </button>
                        </div>
                    </form>
                    <br>
                </div>

                <p class="mt-xl md:hidden font-label-sm text-label-sm text-on-surface-variant">SmartExam Development
                    Team</p>
            </div>
        </section>
    </main>
</x-guest-layout>
