<x-layouts.pengawas title="Dashboard Pengawas">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Selamat datang, {{ auth()->user()->name }}!</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Ruangan Anda: <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $room->display_name }}</span> (kapasitas {{ $room->capacity }} peserta).
            </p>
        </div>

        @if ($schedules->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada jadwal ujian di ruangan Anda hari ini.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($schedules as $schedule)
                    @php
                        $badge = match ($schedule->live_status) {
                            \App\Models\ExamSchedule::STATUS_SCHEDULED => ['belum_mulai', 'Belum Dimulai'],
                            \App\Models\ExamSchedule::STATUS_ONGOING => ['berlangsung', 'Sedang Berlangsung'],
                            default => ['selesai', 'Selesai'],
                        };
                        $stats = $scheduleStats[$schedule->id] ?? null;
                    @endphp

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="truncate text-lg font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Kelas {{ $schedule->class_name }} &middot; {{ $schedule->room?->display_name }}
                                </p>
                            </div>
                            <x-badge-status :status="$badge[0]" :label="$badge[1]" />
                        </div>

                        <div class="mt-3 flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-300">
                            <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-semibold">{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB</span>
                            <span class="text-gray-400 dark:text-gray-500">&middot; {{ $schedule->duration_minutes }} menit</span>
                        </div>

                        @if ($stats)
                            <div class="mt-4 grid grid-cols-3 gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Absen Hadir</p>
                                    <p class="mt-0.5 text-xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['hadir'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">dari {{ $stats['total'] }} peserta</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Sedang Mengerjakan</p>
                                    <p class="mt-0.5 text-xl font-bold text-sky-600 dark:text-sky-400">{{ $stats['sedang_mengerjakan'] }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Sudah Selesai</p>
                                    <p class="mt-0.5 text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['selesai'] }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($activeSchedule !== null)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    Peserta — {{ $activeSchedule->subject?->name }}
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">(Kelas {{ $activeSchedule->class_name }})</span>
                </h3>
                <div class="mt-4">
                    <x-table :headers="['No', 'NISN', 'Nama Peserta', 'Kelas', 'Kehadiran', 'Status Ujian']">
                        @foreach ($students as $index => $student)
                            @php
                                $session = $student->examSessions->first();
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->nisn }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->class_name }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($session?->attendance_status)
                                        <x-badge-status :status="$session->attendance_status" />
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">Belum dicatat</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($session)
                                        <x-badge-status :status="$session->status === 'not_started' ? 'belum_mulai' : $session->status" />
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">Belum ada sesi</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            </div>
        @endif

        <div
            x-data="{
                violations: @js($recentViolations),
                loading: false,
                async refresh() {
                    this.loading = true;
                    try {
                        const res = await fetch('{{ route('pengawas.violations.recent') }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.violations = (await res.json()).violations;
                        }
                    } finally {
                        this.loading = false;
                    }
                },
                async markHandled(id) {
                    const url = @js(route('pengawas.violations.handle', '__ID__'));
                    const res = await fetch(url.replace('__ID__', id), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (res.ok) {
                        const item = this.violations.find((v) => v.id === id);
                        if (item) item.handled = true;
                    }
                },
                init() {
                    this.timer = setInterval(() => this.refresh(), 10000);
                }
            }"
            class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Notifikasi Pelanggaran</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Menyegarkan otomatis setiap 10 detik.</p>
                </div>
                <button type="button" @click="refresh()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700" :disabled="loading">
                    <svg class="h-3.5 w-3.5" :class="loading && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span x-text="loading ? 'Memuat...' : 'Refresh'"></span>
                </button>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                <template x-for="violation in violations" :key="violation.id">
                    <li class="px-5 py-3">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="violation.student_name"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="violation.class_name + ' · ' + violation.subject"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold text-rose-600 dark:text-rose-400" x-text="violation.violation_label"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="violation.occurred_at"></p>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3 border-t border-gray-100 pt-2 dark:border-gray-800">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Checklist:</span>
                                <template x-for="(flag, index) in violation.flags" :key="index">
                                    <span class="inline-block h-3 w-3 rounded-sm" :class="flag ? 'bg-rose-500' : 'bg-gray-200 dark:bg-gray-700'"></span>
                                </template>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="violation.flag_count + ' dari 3'"></span>
                            </div>
                            <button type="button" @click="markHandled(violation.id)"
                                    :class="violation.handled ? 'bg-emerald-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                    x-text="violation.handled ? 'Sudah Ditangani' : 'Tandai Ditangani'"></button>
                        </div>
                    </li>
                </template>
                <li x-show="violations.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada pelanggaran di ruangan Anda.
                </li>
            </ul>
        </div>
    </div>
</x-layouts.pengawas>
