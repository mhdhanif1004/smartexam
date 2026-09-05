<x-layouts.kepala_sekolah title="Dashboard Kepala Sekolah">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Dashboard Kepala Sekolah</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan data akademik dan pelaksanaan ujian hari ini.</p>
        </div>

        {{-- Baris 1: Ringkasan utama (kartu Total Siswa/Pengawas/Guru Mapel ditautkan ke halaman Data masing-masing) --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('kepala_sekolah.students.index') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Total Siswa" :value="number_format($totalStudents)" color="indigo" icon="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            </a>
            <a href="{{ route('kepala_sekolah.supervisors.index') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Total Pengawas" :value="number_format($totalSupervisors)" color="emerald" icon="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
            </a>
            <a href="{{ route('kepala_sekolah.guru-mapels.index') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Total Guru Mapel" :value="number_format($totalGuruMapels)" color="amber" icon="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            </a>
            <x-card-stat label="Total Mata Pelajaran" :value="number_format($totalSubjects)" color="sky" icon="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </div>

        {{-- Baris 2: Kehadiran — 4 card ditautkan ke halaman detail (attendance_status, hidden dari sidebar) --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('kepala_sekolah.attendance.students.present') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Hadir Siswa" :value="number_format($studentPresentCount)" color="emerald" icon="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </a>
            <a href="{{ route('kepala_sekolah.attendance.students.absent') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Tidak Hadir Siswa" :value="number_format($studentAbsentCount)" color="rose" icon="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </a>
            <a href="{{ route('kepala_sekolah.attendance.supervisors.present') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Hadir Pengawas" :value="number_format($supervisorPresentCount)" color="sky" icon="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </a>
            <a href="{{ route('kepala_sekolah.attendance.supervisors.absent') }}" class="block rounded-xl transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-card-stat label="Tidak Hadir Pengawas" :value="number_format($supervisorAbsentCount)" color="amber" icon="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </a>
        </div>

        {{-- Baris 3: Ujian hari ini & ruangan/soal --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-card-stat label="Ujian Hari Ini" :value="number_format($todaySchedules)" color="rose" icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            <x-card-stat label="Total Ruangan" :value="number_format($totalRooms)" color="indigo" icon="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
        </div>

        {{-- Section chart: 2 kolom — kiri donut --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Distribusi Kelulusan</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Perbandingan peserta lulus dan tidak lulus.</p>
                <div class="mt-4">
                    @if ($hasData)
                        @php
                            $totalDonut = ($donutData[0] ?? 0) + ($donutData[1] ?? 0);
                            $pctLulus = $totalDonut > 0 ? round((($donutData[0] ?? 0) / $totalDonut) * 100, 1) : 0;
                            $pctTidak = $totalDonut > 0 ? round((($donutData[1] ?? 0) / $totalDonut) * 100, 1) : 0;
                        @endphp
                        @if ($totalDonut === 0)
                            <div class="rounded-xl border bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Belum ada data lulus/tidak lulus</div>
                        @else
                        <div class="flex flex-col items-center gap-6 sm:flex-row">
                            <div class="relative h-56 w-56 shrink-0" style="min-height:14rem;min-width:14rem;">
                                <canvas id="chart-donut" class="h-full w-full"></canvas>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 shrink-0 rounded-full" style="background:#34d399"></span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Lulus {{ $donutData[0] ?? 0 }} ({{ $pctLulus }}%)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 shrink-0 rounded-full" style="background:#f87171"></span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Tidak Lulus {{ $donutData[1] ?? 0 }} ({{ $pctTidak }}%)</span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Rata-rata: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $average }}</span></p>
                            </div>
                        </div>
                        @endif
                    @else
                        <div class="rounded-xl border bg-white p-8 text-center text-gray-500">Belum ada data nilai</div>
                    @endif
                </div>
            </div>

            {{-- Kolom kanan: ringkasan tambahan (read-only) --}}
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Ringkasan Soal & Ruangan</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Informasi tambahan pelaksanaan ujian.</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800">
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalRooms) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Ruangan</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800">
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalQuestions) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Soal</p>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-4 text-center dark:bg-indigo-500/10">
                        <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($todaySchedules) }}</p>
                        <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Ujian Hari Ini</p>
                    </div>
                    <div class="rounded-lg bg-rose-50 p-4 text-center dark:bg-rose-500/10">
                        <p class="text-2xl font-bold text-rose-700 dark:text-rose-300">{{ count($recentViolations) }}</p>
                        <p class="text-xs font-medium text-rose-600 dark:text-rose-400">Pelanggaran Terbaru</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section Mata Pelajaran Hari Ini (unik) --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Mata Pelajaran Hari Ini</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Daftar mata pelajaran yang diujikan hari ini (unik).</p>
            </div>
            @if ($subjectsToday->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada mata pelajaran diujikan hari ini</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($subjectsToday as $index => $subject)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $index + 1 }}</span>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $subject->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Section pelanggaran --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pelanggaran Terbaru</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">5 pelanggaran terakhir yang tercatat.</p>
            </div>
            @if (count($recentViolations) > 0)
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($recentViolations as $violation)
                        <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $violation['student_name'] ?? '-' }} — {{ $violation['class_name'] ?? '-' }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $violation['subject'] ?? '-' }} · {{ $violation['room_name'] ?? '-' }}</p>
                            </div>
                            <div class="shrink-0 text-left sm:text-right">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $violation['violation_label'] ?? '-' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $violation['occurred_at'] ?? '-' }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada pelanggaran</div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        const isDarkMode = () => document.documentElement.classList.contains('dark');
        const chartBorder = () => (isDarkMode() ? '#111827' : '#ffffff');

        const centerTextPlugin = {
            id: 'centerText',
            beforeDraw(chart) {
                const opts = chart.options.plugins.centerText;
                if (!opts || !opts.display) return;
                const ctx = chart.ctx;
                const {width, height} = chart;
                ctx.save();
                const fontSize = (height / 114).toFixed(2);
                ctx.font = `bold ${fontSize}em sans-serif`;
                ctx.textBaseline = 'middle';
                ctx.textAlign = 'center';
                const text = String(opts.text ?? '');
                const centerX = width / 2;
                const centerY = height / 2;
                ctx.fillStyle = opts.color || (isDarkMode() ? '#d1d5db' : '#374151');
                ctx.fillText(text, centerX, centerY);
                ctx.restore();
            }
        };

        let ksDonutChart = null;
        let ksDonutRetry = 0;
        function getKsChart() {
            return window.Chart ?? null;
        }
        async function renderDonut() {
            const canvas = document.getElementById('chart-donut');
            if (!canvas) return;
            const hasData = @js($hasData);
            if (!hasData) return;
            const labels = @json($donutLabels);
            const data = @json($donutData);
            const average = @json($average);
            const totalDonut = (data[0] ?? 0) + (data[1] ?? 0);
            const placeholderId = 'chart-donut-placeholder-zero';
            let placeholder = document.getElementById(placeholderId);
            if (totalDonut === 0) {
                canvas.style.display = 'none';
                if (!placeholder) {
                    placeholder = document.createElement('div');
                    placeholder.id = placeholderId;
                    placeholder.className = 'rounded-xl border bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400';
                    placeholder.textContent = 'Belum ada data lulus/tidak lulus';
                    canvas.parentElement?.appendChild(placeholder);
                } else { placeholder.style.display = 'block'; }
                if (canvas._chart) { try { canvas._chart.destroy(); } catch(e) {} canvas._chart = null; }
                if (ksDonutChart) { try { ksDonutChart.destroy(); } catch(e) {} ksDonutChart = null; }
                return;
            } else {
                if (placeholder) placeholder.style.display = 'none';
                canvas.style.display = 'block';
            }
            if (canvas.clientWidth === 0 || canvas.clientHeight === 0) {
                if (ksDonutRetry < 12) { ksDonutRetry++; requestAnimationFrame(() => { renderDonut(); }); }
                return;
            }
            ksDonutRetry = 0;
            const ChartLib = getKsChart();
            if (!ChartLib) {
                if (ksDonutRetry < 6) { ksDonutRetry++; setTimeout(() => renderDonut(), 180); }
                return;
            }
            if (canvas._chart) { try { canvas._chart.destroy(); } catch(e) {} canvas._chart = null; }
            if (ksDonutChart) { try { ksDonutChart.destroy(); } catch(e) {} ksDonutChart = null; }
            const ctx = canvas.getContext('2d');
            const inst = new ChartLib(ctx, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#34d399', '#f87171'], borderColor: chartBorder(), borderWidth: 2 }] },
                plugins: [centerTextPlugin],
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, centerText: { display: true, text: average, color: isDarkMode() ? '#d1d5db' : '#374151' }, tooltip: { enabled: true } } },
            });
            canvas._chart = inst; ksDonutChart = inst;
        }
        function scheduleKsDonut(){ requestAnimationFrame(() => { renderDonut(); }); }
        document.addEventListener('DOMContentLoaded', scheduleKsDonut);
        document.addEventListener('turbo:load', scheduleKsDonut);
        document.addEventListener('turbo:render', scheduleKsDonut);
        window.addEventListener('themechange', scheduleKsDonut);
        window.addEventListener('load', scheduleKsDonut);
        setTimeout(scheduleKsDonut, 320);
    </script>
    @endpush
</x-layouts.kepala_sekolah>
