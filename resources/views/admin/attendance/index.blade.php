<x-layouts.admin title="Absensi">
    <div
        x-data="{
            autoRefresh: true,
            timer: null,
            openPeriods: @js($periods->mapWithKeys(fn ($period, $index) => [$index => $period['status'] !== 'finished'])->all()),
            openRooms: {},
            togglePeriod(index) {
                this.openPeriods[index] = !this.openPeriods[index];
            },
            toggleRoom(key) {
                this.openRooms[key] = !this.openRooms[key];
            },
            isOpenPeriod(index) {
                return Boolean(this.openPeriods[index]);
            },
            isOpenRoom(key) {
                return Boolean(this.openRooms[key]);
            },
            toggleRefresh() {
                this.autoRefresh = !this.autoRefresh;
                if (this.autoRefresh) {
                    this.startTimer();
                } else {
                    this.clearTimer();
                }
            },
            startTimer() {
                this.timer = setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        window.location.reload();
                    }
                }, 15000);
            },
            clearTimer() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            },
            init() {
                this.startTimer();
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Absensi Ujian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan kehadiran peserta dan pengawas per sesi dan ruangan.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d M Y') }}
            </span>
        </div>

        @include('admin.partials.flash')

        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label for="date" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Ujian</label>
                <input type="date" name="date" id="date" value="{{ $date }}" onchange="window.location.href = '{{ route('admin.attendance.index') }}?date=' + this.value" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>
            <a href="{{ route('admin.attendance.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Hari Ini</a>
            <button type="button" @click="toggleRefresh()" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-medium transition" :class="autoRefresh ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span x-text="autoRefresh ? 'Auto-refresh aktif (15 dtk)' : 'Auto-refresh nonaktif'"></span>
            </button>
        </div>

        @if ($periods->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Tidak ada sesi ujian pada tanggal ini.</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih tanggal lain menggunakan filter di atas untuk melihat jadwal ujian.</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-card-stat label="Peserta Hadir" :value="$totals['present']" color="emerald" icon="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                <x-card-stat label="Peserta Tidak Hadir" :value="$totals['absent']" color="rose" icon="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                <x-card-stat label="Pengawas Hadir" :value="$totals['supervisorPresent']" color="emerald" icon="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                <x-card-stat label="Pengawas Tidak Hadir" :value="$totals['supervisorAbsent']" color="rose" icon="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0112 21.75c-2.973 0-5.703-.92-8-2.515zM18.75 3v4.5m0 0h4.5m-4.5 0H14.25m4.5 0l-4.5 4.5" />
            </div>

            @foreach ($periods as $periodIndex => $period)
                @php
                    $periodStatus = $period['status'];
                    $periodBadge = match ($periodStatus) {
                        \App\Models\ExamSchedule::STATUS_ONGOING => 'berlangsung',
                        \App\Models\ExamSchedule::STATUS_FINISHED => 'selesai',
                        default => 'terjadwal',
                    };
                    $periodPresent = $period['rooms']->sum('present');
                    $periodAbsent = $period['rooms']->sum('absent');
                    $periodSupervisorPresent = $period['rooms']->sum('supervisorPresent');
                    $periodSupervisorAbsent = $period['rooms']->sum('supervisorAbsent');
                @endphp

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <button type="button" @click="togglePeriod({{ $periodIndex }})" class="flex w-full flex-col gap-3 px-5 py-4 text-left sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $period['name'] }}</h3>
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $period['dateLabel'] }}</span>
                            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $period['start'] }} - {{ $period['end'] }} WIB</span>
                            <x-badge-status :status="$periodBadge" :label="\App\Models\ExamSchedule::STATUSES[$periodStatus] ?? $periodStatus" />
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="text-emerald-600 dark:text-emerald-400">Hadir <strong>{{ $periodPresent }}</strong></span>
                            <span class="text-rose-600 dark:text-rose-400">Tidak Hadir <strong>{{ $periodAbsent }}</strong></span>
                            <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                            <span class="text-gray-500 dark:text-gray-400">Pengawas <strong class="text-emerald-600 dark:text-emerald-400">{{ $periodSupervisorPresent }}</strong>/<strong class="text-rose-600 dark:text-rose-400">{{ $periodSupervisorAbsent }}</strong></span>
                            <svg class="h-4 w-4 text-gray-400 transition-transform" :class="isOpenPeriod({{ $periodIndex }}) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </button>

                    <div x-show="isOpenPeriod({{ $periodIndex }})" x-transition x-cloak class="border-t border-gray-200 p-4 dark:border-gray-800">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($period['rooms'] as $roomIndex => $room)
                                @php
                                    $roomKey = $periodIndex.'-'.$roomIndex;
                                    $roomTotal = $room['present'] + $room['absent'] + $room['unchecked'];
                                    $supervisorUnchecked = $room['supervisorTotal'] - $room['supervisorPresent'] - $room['supervisorAbsent'];
                                    $needsAttention = $room['supervisorAbsent'] > 0 && in_array($periodStatus, [\App\Models\ExamSchedule::STATUS_ONGOING, \App\Models\ExamSchedule::STATUS_FINISHED], true);
                                @endphp

                                <div class="{{ $needsAttention ? 'border-amber-400 bg-amber-50 dark:border-amber-500/50 dark:bg-amber-500/5' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' }} overflow-hidden rounded-xl border shadow-sm">
                                    <button type="button" @click="toggleRoom(@js($roomKey))" class="w-full px-4 py-3 text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $room['room']?->name ?? 'Tanpa Ruangan' }}</h4>
                                            <svg class="h-4 w-4 text-gray-400 transition-transform" :class="isOpenRoom(@js($roomKey)) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-3">
                                            <div class="rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-500/10">
                                                <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Peserta Hadir</p>
                                                <p class="text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ $room['present'] }} <span class="text-sm font-medium text-emerald-500 dark:text-emerald-400">/ {{ $roomTotal }}</span></p>
                                            </div>
                                            <div class="rounded-lg bg-rose-50 px-3 py-2 dark:bg-rose-500/10">
                                                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">Peserta Tidak Hadir</p>
                                                <p class="text-xl font-bold text-rose-700 dark:text-rose-300">{{ $room['absent'] }} <span class="text-sm font-medium text-rose-500 dark:text-rose-400">/ {{ $roomTotal }}</span></p>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Pengawas: <strong class="text-emerald-600 dark:text-emerald-400">{{ $room['supervisorPresent'] }}</strong> hadir &middot; <strong class="text-rose-600 dark:text-rose-400">{{ $room['supervisorAbsent'] }}</strong> tidak hadir
                                            @if ($supervisorUnchecked > 0)
                                                &middot; <span class="text-gray-400 dark:text-gray-500">{{ $supervisorUnchecked }} belum dicek</span>
                                            @endif
                                        </div>
                                        @if ($room['unchecked'] > 0)
                                            <p class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400">{{ $room['unchecked'] }} peserta belum dicek pengawas.</p>
                                        @endif
                                        @if ($needsAttention)
                                            <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-amber-700 dark:text-amber-400">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                </svg>
                                                Perhatian: pengawas tidak hadir saat ujian berlangsung/selesai.
                                            </p>
                                        @endif
                                    </button>

                                    <div x-show="isOpenRoom(@js($roomKey))" x-transition x-cloak class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                                        @foreach ($room['schedules'] as $schedulePayload)
                                            @php
                                                $schedule = $schedulePayload['schedule'];
                                                $scheduleStatus = $schedulePayload['status'];
                                                $scheduleBadge = match ($scheduleStatus) {
                                                    \App\Models\ExamSchedule::STATUS_ONGOING => 'berlangsung',
                                                    \App\Models\ExamSchedule::STATUS_FINISHED => 'selesai',
                                                    default => 'terjadwal',
                                                };
                                            @endphp

                                            <div class="mb-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                                                <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <h5 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h5>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            Kelas {{ $schedule->class_name }} &middot; {{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB
                                                        </p>
                                                    </div>
                                                    <x-badge-status :status="$scheduleBadge" :label="\App\Models\ExamSchedule::STATUSES[$scheduleStatus] ?? $scheduleStatus" />
                                                </div>

                                                <div class="px-4 pb-4">
                                                    <x-table :headers="['NISN', 'Nama Peserta', 'Kelas', 'Kehadiran']">
                                                        @forelse ($schedulePayload['participants'] as $item)
                                                            @php
                                                                $attendanceStatus = $item['session']?->attendance_status;
                                                            @endphp
                                                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $item['student']->nisn }}</td>
                                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['student']->user?->name }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $item['student']->class_name }}</td>
                                                                <td class="px-4 py-3 text-sm">
                                                                    @if ($attendanceStatus)
                                                                        <x-badge-status :status="$attendanceStatus" />
                                                                    @else
                                                                        <span class="text-xs text-gray-400 dark:text-gray-500">Belum dicek</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada peserta yang ditempatkan di ruangan ini.</td>
                                                            </tr>
                                                        @endforelse
                                                    </x-table>

                                                    <div class="mt-3">
                                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Pengawas Ruangan</p>
                                                        @if ($schedulePayload['supervisors']->isEmpty())
                                                            <p class="text-xs text-gray-400 dark:text-gray-500">Belum ada pengawas yang ditugaskan di ruangan ini.</p>
                                                        @else
                                                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                                                @foreach ($schedulePayload['supervisors'] as $supervisorItem)
                                                                    @php
                                                                        $supervisorAttendance = $supervisorItem['attendance'];
                                                                        $supervisorStatus = $supervisorAttendance?->status;
                                                                    @endphp
                                                                    <li class="flex items-center gap-3 py-2">
                                                                        <div class="min-w-0 flex-1">
                                                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $supervisorItem['supervisor']->user?->name }}</p>
                                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                                @if ($supervisorStatus === \App\Models\SupervisorAttendance::STATUS_PRESENT)
                                                                                    Check-in: {{ $supervisorAttendance->checked_in_at?->format('d M H:i') }}
                                                                                @elseif ($supervisorStatus === \App\Models\SupervisorAttendance::STATUS_ABSENT)
                                                                                    Tidak hadir pada sesi ini.
                                                                                @else
                                                                                    Belum dicek.
                                                                                @endif
                                                                            </p>
                                                                        </div>
                                                                        @if ($supervisorStatus)
                                                                            <x-badge-status :status="$supervisorStatus" />
                                                                        @else
                                                                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Belum dicek</span>
                                                                        @endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-layouts.admin>
