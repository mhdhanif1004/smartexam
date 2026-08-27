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

    </div>
</x-layouts.pengawas>
