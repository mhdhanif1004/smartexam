<div class="space-y-3">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)"
             role="alert"
             class="flex items-start gap-3 rounded-lg border-l-4 border-emerald-200 border-l-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:border-l-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-300">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="flex-1">{{ session('success') }}</p>
            <button type="button" @click="show = false" aria-label="Tutup notifikasi"
                    class="shrink-0 rounded-md p-1 text-emerald-600 transition hover:bg-emerald-100 dark:text-emerald-400 dark:hover:bg-emerald-500/20">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if (session('warning'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 7000)"
             role="alert"
             class="flex items-start gap-3 rounded-lg border-l-4 border-amber-200 border-l-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:border-l-amber-500 dark:bg-amber-500/10 dark:text-amber-300">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p class="flex-1">{{ session('warning') }}</p>
            <button type="button" @click="show = false" aria-label="Tutup notifikasi"
                    class="shrink-0 rounded-md p-1 text-amber-600 transition hover:bg-amber-100 dark:text-amber-400 dark:hover:bg-amber-500/20">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 10000)"
             role="alert"
             class="flex items-start gap-3 rounded-lg border-l-4 border-rose-200 border-l-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:border-l-rose-500 dark:bg-rose-500/10 dark:text-rose-300">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-500 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374H4.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0l7.303 13.748zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p class="flex-1">{{ session('error') }}</p>
            <button type="button" @click="show = false" aria-label="Tutup notifikasi"
                    class="shrink-0 rounded-md p-1 text-rose-600 transition hover:bg-rose-100 dark:text-rose-400 dark:hover:bg-rose-500/20">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
</div>
