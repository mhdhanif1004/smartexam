<footer class="relative overflow-hidden border-t border-outline-variant bg-surface-container-low">
    {{-- Garis aksen gradient di atas footer (dekoratif, bukan teks) --}}
    <div class="absolute inset-x-0 top-0 h-px pointer-events-none"
        style="background: linear-gradient(90deg, transparent, rgb(var(--color-primary) / 0.55), transparent);"></div>

    <div class="w-full max-w-container-max mx-auto px-lg py-xl">
        <div class="flex flex-col gap-lg md:flex-row md:items-center md:justify-between">

            {{-- Branding teks (kata dipertahankan) --}}
            <span class="font-title-md text-title-md font-semibold text-on-surface">SmartExam
                Development Team</span>

            {{-- Navigasi footer (kata dipertahankan) --}}
            <nav class="flex flex-wrap justify-center gap-lg" aria-label="Navigasi footer">
                <a href="{{ route('privacy-policy') }}"
                    class="font-label-sm text-label-sm text-on-surface-variant hover:underline hover:text-primary transition-all duration-200">Privacy
                    Policy</a>
                <a href="{{ route('terms-of-service') }}"
                    class="font-label-sm text-label-sm text-on-surface-variant hover:underline hover:text-primary transition-all duration-200">Terms
                    of Service</a>
                <a href="{{ route('cbt-guidelines') }}"
                    class="font-label-sm text-label-sm text-on-surface-variant hover:underline hover:text-primary transition-all duration-200">CBT
                    Guidelines</a>
            </nav>

            {{-- Copyright (kata dipertahankan) --}}
            <p class="text-center md:text-right font-body-md text-body-md text-secondary">© 2026 Erwa & Hanif
                Development. All rights reserved.</p>
        </div>
    </div>
</footer>
