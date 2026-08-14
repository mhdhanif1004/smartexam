<x-layouts.admin title="Dashboard Admin">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Selamat datang, {{ auth()->user()->name }}!</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan aktivitas Sistem CBT SmartExam hari ini.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-card-stat label="Siswa" :value="number_format($totalStudents)" color="indigo" icon="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            <x-card-stat label="Pengawas" :value="number_format($totalSupervisors)" color="emerald" icon="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
            <x-card-stat label="Mata Pelajaran" :value="number_format($totalSubjects)" color="amber" icon="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
            <x-card-stat label="Ujian Hari Ini" :value="number_format($examsToday)" color="rose" icon="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            <x-card-stat label="Hadir" :value="number_format($attendancePresentCount)" color="emerald" icon="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            <x-card-stat label="Tidak Hadir" :value="number_format($attendanceAbsentCount)" color="rose" icon="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Tren Jumlah Ujian (7 Hari Terakhir)</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Banyaknya jadwal ujian per hari.</p>
                <div class="mt-4 h-64">
                    <canvas id="chart-trend"></canvas>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Distribusi Nilai Peserta</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Sebaran nilai hasil ujian.</p>
                <div class="mt-4 h-64">
                    <canvas id="chart-distribution"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Jadwal Ujian Terdekat</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">5 jadwal berikutnya dari hari ini.</p>
                    </div>
                    <a href="{{ route('admin.exam-schedules.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Lihat semua</a>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($upcomingSchedules as $schedule)
                        <li class="flex items-center gap-4 px-5 py-3">
                            <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                <span class="text-sm font-bold leading-none">{{ $schedule->exam_date->format('d') }}</span>
                                <span class="text-[10px] uppercase leading-none">{{ $schedule->exam_date->format('M') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $schedule->class_name }} &middot; {{ $schedule->room?->display_name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }}</p>
                                @php
                                    $computedStatus = $schedule->computedStatus();
                                @endphp
                                <x-badge-status :status="$computedStatus" :label="\App\Models\ExamSchedule::STATUSES[$computedStatus] ?? $computedStatus" />
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada jadwal ujian.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pelanggaran Terbaru</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">5 pelanggaran terakhir yang dilaporkan.</p>
                    </div>
                    <a href="{{ route('admin.violations.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Lihat semua</a>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($recentViolations as $violation)
                        <li class="flex items-center gap-4 px-5 py-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $violation->examSession?->student?->user?->name ?? '-' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $violation->examSession?->examSchedule?->subject?->name ?? '-' }} &middot; {{ $violation->examSession?->student?->class_name ?? '' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold text-rose-600 dark:text-rose-400">{{ \App\Models\Violation::typeLabel($violation->violation_type) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $violation->occurred_at->format('d M H:i') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada pelanggaran.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Absensi Pengawas Terbaru</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">5 absensi pengawas terakhir.</p>
                    </div>
                    <a href="{{ route('admin.attendance.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Lihat semua</a>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($recentSupervisorAttendances as $attendance)
                        @php
                            $isPresent = $attendance->status === \App\Models\SupervisorAttendance::STATUS_PRESENT;
                            $statusText = $isPresent ? 'Hadir' : 'Tidak Hadir';
                            $statusColor = $isPresent ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
                            $bgColor = $isPresent ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-rose-50 dark:bg-rose-500/10';
                        @endphp
                        <li class="flex items-center gap-4 px-5 py-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $bgColor }}">
                                <svg class="h-5 w-5 {{ $statusColor }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    @if ($isPresent)
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                    @endif
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $attendance->supervisor->user->name ?? '-' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $attendance->room->display_name ?? '-' }} &middot; {{ $attendance->examSchedule->subject->name ?? '-' }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $bgColor }} {{ $statusColor }}">
                                    {{ $statusText }}
                                </span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $attendance->checked_in_at->format('d M H:i') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data absensi pengawas.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <script>
        const isDarkMode = () => document.documentElement.classList.contains('dark');
        const gridColor = () => (isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)');
        const tickColor = () => (isDarkMode() ? '#9ca3af' : '#6b7280');
        const legendColor = () => (isDarkMode() ? '#d1d5db' : '#374151');
        const chartBorder = () => (isDarkMode() ? '#111827' : '#ffffff');

        let trendChart = null;
        let distributionChart = null;

        function renderCharts() {
            const trendCanvas = document.getElementById('chart-trend');
            if (trendCanvas && window.Chart) {
                if (trendChart) trendChart.destroy();
                trendChart = new Chart(trendCanvas, {
                    type: 'bar',
                    data: {
                        labels: @json($chartTrendLabels),
                        datasets: [{
                            label: 'Jumlah Ujian',
                            data: @json($chartTrendData),
                            backgroundColor: 'rgba(79, 70, 229, 0.75)',
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: gridColor() }, ticks: { color: tickColor() } },
                            y: { beginAtZero: true, grid: { color: gridColor() }, ticks: { color: tickColor(), precision: 0 } },
                        },
                    },
                });
            }

            const distributionCanvas = document.getElementById('chart-distribution');
            if (distributionCanvas && window.Chart) {
                if (distributionChart) distributionChart.destroy();
                distributionChart = new Chart(distributionCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: @json($distributionLabels),
                        datasets: [{
                            data: @json($distributionData),
                            backgroundColor: ['#f87171', '#fbbf24', '#facc15', '#34d399', '#6366f1'],
                            borderColor: chartBorder(),
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: legendColor() } } },
                    },
                });
            }
        }

        document.addEventListener('DOMContentLoaded', renderCharts);
        window.addEventListener('themechange', renderCharts);
    </script>
</x-layouts.admin>
