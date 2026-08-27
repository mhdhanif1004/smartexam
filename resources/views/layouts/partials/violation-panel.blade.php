{{-- ============================================================
     Partial: Panel Notifikasi Pelanggaran (shared oleh admin & pengawas)
     Variables:
       $pollingEndpoint  — URL endpoint polling (per role)
       $handleEndpoint   — URL endpoint mark-handled (per role), null = read-only
     ============================================================ --}}
<div
    x-data="violationPolling({
        endpoint: '{{ $pollingEndpoint }}',
        csrf: '{{ csrf_token() }}',
        csrfUrl: '{{ route('csrf-token') }}',
        handleUrl: @js($handleEndpoint),
        initialViolations: [],
    })"
    class="border-t border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
>
    <div class="flex items-center justify-between px-5 py-3">
        <div class="flex items-center gap-3">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Notifikasi Pelanggaran</h3>
            <span x-show="badgeCount > 0" x-text="badgeCount" @click="dismissBadge()"
                  class="inline-flex h-5 min-w-[20px] cursor-pointer items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white"></span>
        </div>
        <div class="flex items-center gap-2">
            <span x-show="loading" class="inline-flex items-center gap-1 text-[10px] text-gray-400 dark:text-gray-500">
                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Memuat...
            </span>
            <button type="button" @click="refreshPoll()" class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <div x-show="permissionStatus === 'default'" class="border-t border-amber-200 bg-amber-50 px-5 py-2 dark:border-amber-800 dark:bg-amber-500/10">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-amber-700 dark:text-amber-300">Aktifkan notifikasi browser untuk peringatan pelanggaran real-time.</p>
            <button type="button" @click="requestPermission()" class="shrink-0 rounded-lg bg-amber-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-amber-500">Aktifkan</button>
        </div>
    </div>

    <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
        <template x-for="violation in violations" :key="violation.id">
            <li class="flex items-center gap-3 px-5 py-2.5 transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-gray-900 dark:text-gray-100" x-text="violation.student_name"></p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400" x-text="violation.room_name + ' \u00b7 ' + violation.violation_label"></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-semibold text-rose-600 dark:text-rose-400" x-text="violation.violation_label"></p>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500" x-text="violation.occurred_at"></p>
                </div>
                @if($handleEndpoint)
                    <button type="button" @click="markHandled(violation.id)"
                            :class="violation.handled ? 'bg-emerald-600 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'"
                            class="inline-flex shrink-0 items-center rounded-md px-2 py-1 text-[10px] font-semibold transition"
                            x-text="violation.handled ? 'Ditangani' : 'Tandai'"></button>
                @endif
            </li>
        </template>
        <li x-show="violations.length === 0" class="px-5 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
            Tidak ada pelanggaran aktif.
        </li>
    </ul>
</div>
