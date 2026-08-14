<x-layouts.peserta title="Masuk Ujian">
    <div class="mx-auto max-w-lg space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Masuk Ujian</p>
            <h2 class="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Kelas</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $schedule->class_name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Ruangan</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $schedule->room?->display_name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Waktu</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Durasi</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $schedule->duration_minutes }} menit</dd>
                </div>
            </dl>
        </div>

        @include('admin.partials.flash')

        @if (! empty($accessError))
            <div class="rounded-xl border-2 border-rose-300 bg-rose-50 p-6 shadow-sm dark:border-rose-700 dark:bg-rose-500/10" role="alert">
                <div class="flex gap-3">
                    <svg class="h-6 w-6 shrink-0 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374H4.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0l7.303 13.748zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <div>
                        <h3 class="text-base font-bold text-rose-800 dark:text-rose-300">Token Ujian Tidak Dapat Diproses</h3>
                        <p class="mt-1 text-sm text-rose-700 dark:text-rose-400">{{ $accessError }}</p>
                    </div>
                </div>
                <a href="{{ route('peserta.dashboard') }}" class="mt-4 inline-block rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-600 dark:bg-gray-800 dark:text-rose-300 dark:hover:bg-gray-700">Kembali ke Dashboard</a>
            </div>
        @else
        <form method="POST" action="{{ route('peserta.exams.token.validate', $schedule->id) }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @csrf
            <label for="token_code" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Token Ujian</label>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Masukkan token yang diumumkan oleh pengawas ruangan.</p>
            <input
                id="token_code"
                name="token_code"
                type="text"
                required
                autocomplete="off"
                maxlength="16"
                placeholder="XXXX-XXXX"
                class="block w-full rounded-md border-gray-300 text-center font-mono text-2xl uppercase tracking-widest shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            >
            <x-input-error :messages="$errors->get('token_code')" class="mt-2" />

            <div class="mt-5 flex items-center gap-3">
                <a href="{{ route('peserta.dashboard') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
                <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    Masuk Ujian
                </button>
            </div>
        </form>
        @endif
    </div>
</x-layouts.peserta>
