<x-layouts.admin :title="'Detail '.$room->display_name">
    <div
        x-data="{
            openPeriods: {},
            togglePeriod(id) {
                this.openPeriods[id] = !this.openPeriods[id];
            },
            isOpen(id) {
                return Boolean(this.openPeriods[id]);
            },
            openSchedules: {},
            toggleSchedule(id) {
                this.openSchedules[id] = !this.openSchedules[id];
            },
            isScheduleOpen(id) {
                return Boolean(this.openSchedules[id]);
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Detail {{ $room->display_name }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Peserta dan pengawas yang pernah/akan di-assign ke ruangan ini.</p>
            </div>
            <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <x-card-stat label="Total Sesi" :value="$totalSessions" color="indigo" icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            <x-card-stat label="Total Peserta" :value="$totalStudents" color="emerald" icon="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            <x-card-stat label="Total Pengawas" :value="$totalSupervisors" color="amber" icon="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            <x-card-stat label="Total Hari" :value="$totalDays" color="indigo" icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
        </div>

        {{-- Section Peserta --}}
        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Peserta Ujian</h3>

            @if ($assignmentsByPeriod->isEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada peserta yang ditempatkan di ruangan ini.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($assignmentsByPeriod as $periodAssignments)
                        @php($period = $periodAssignments->first()?->examPeriod)
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <button type="button" @click="togglePeriod(@js($period?->id))" class="flex w-full items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 text-left dark:border-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $period?->name ?? 'Tanpa Sesi' }}</h3>
                                    @if ($period)
                                        <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $period->exam_date->format('d M Y') }}</span>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ \Illuminate\Support\Str::substr($period->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($period->end_time, 0, 5) }}</span>
                                    @endif
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $periodAssignments->count() }} siswa</span>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="isOpen(@js($period?->id)) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <div x-show="isOpen(@js($period?->id))" x-transition x-cloak class="border-t border-gray-200 p-4 dark:border-gray-800">
                                <x-table :headers="['No', 'NISN', 'Nama Siswa', 'Kelas', 'Kursi']">
                                    @foreach ($periodAssignments as $index => $assignment)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->student?->nisn }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment->student?->user?->name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->student?->class_name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->seat_number }}</td>
                                        </tr>
                                    @endforeach
                                </x-table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Section Pengawas --}}
        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Pengawas Ujian</h3>

            @if ($supervisorAssignmentsByDate->isEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pengawas ditugaskan di ruangan ini.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($supervisorAssignmentsByDate as $dateGroup)
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            {{-- Level 1: Hari/Tanggal --}}
                            <button type="button" @click="togglePeriod('sup-{{ $dateGroup['date']->toDateString() }}')" class="flex w-full items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 text-left dark:border-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $dateGroup['date']->translatedFormat('l, d M Y') }}</h3>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $dateGroup['session_count'] }} sesi</span>
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $dateGroup['supervisor_count'] }} pengawas</span>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="isOpen(@js('sup-'.$dateGroup['date']->toDateString())) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            {{-- Level 2: Sesi --}}
                            <div x-show="isOpen(@js('sup-'.$dateGroup['date']->toDateString()))" x-transition x-cloak class="border-t border-gray-200 p-4 dark:border-gray-800">
                                <div class="space-y-2">
                                    @foreach ($dateGroup['sessions'] as $session)
                                        @php($period = $session['period'])
                                        <div class="rounded-lg border border-gray-100 dark:border-gray-800">
                                            <button type="button" @click="toggleSchedule(@js('sup-'.$dateGroup['date']->toDateString().'-'.($period?->id ?? 'x')))" class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left transition hover:bg-gray-50/60 dark:hover:bg-gray-800/30">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <svg class="h-3 w-3 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500" :class="isScheduleOpen(@js('sup-'.$dateGroup['date']->toDateString().'-'.($period?->id ?? 'x'))) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $period?->name ?? 'Tanpa Sesi' }}</span>
                                                    @if ($period)
                                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ \Illuminate\Support\Str::substr($period->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($period->end_time, 0, 5) }}</span>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ count($session['supervisor_names']) }} pengawas</span>
                                            </button>
                                            <div x-show="isScheduleOpen(@js('sup-'.$dateGroup['date']->toDateString().'-'.($period?->id ?? 'x')))" x-transition x-cloak class="border-t border-gray-50 px-4 py-2.5 dark:border-gray-800/50">
                                                @if (empty($session['supervisor_names']))
                                                    <p class="text-xs text-gray-400 dark:text-gray-500">Belum ada pengawas ditugaskan</p>
                                                @else
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach ($session['supervisor_names'] as $supervisorName)
                                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-[10px] font-bold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">{{ strtoupper(substr($supervisorName, 0, 1)) }}</span>
                                                                {{ $supervisorName }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
