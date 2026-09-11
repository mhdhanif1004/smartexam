<x-layouts.wali_kelas title="Dashboard Wali Kelas">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Dashboard Wali Kelas</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pantau kelas yang Anda ampu sebagai wali kelas.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('wali_kelas.export-excel') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export Excel
                </a>
                <x-semester-selector :semesters="$semesters" :selected-semester-id="$selectedSemesterId" />
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kelas</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $wali->classroom?->name ?? 'Belum ditugaskan' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Jumlah Siswa</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $studentCount }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rata-rata Nilai Akhir</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    @if ($averageGrade !== null)
                        {{ number_format($averageGrade, 2) }}
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Pelanggaran</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalViolations }}</p>
            </div>
        </div>

        {{-- Siswa Perlu Perhatian --}}
        <div class="rounded-xl border {{ $siswaPerluPerhatian ? 'border-amber-200 dark:border-amber-800' : 'border-gray-200 dark:border-gray-800' }} bg-white p-6 shadow-sm dark:bg-gray-900">
            <p class="text-sm font-medium {{ $siswaPerluPerhatian ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Siswa Perlu Perhatian</p>
            <p class="mt-1 text-2xl font-bold {{ $siswaPerluPerhatian ? 'text-amber-700 dark:text-amber-300' : 'text-gray-900 dark:text-gray-100' }}">
                {{ count($siswaPerluPerhatian) }}
            </p>
            @if ($siswaPerluPerhatian)
                <ul class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                    @foreach (array_slice($siswaPerluPerhatian, 0, 5) as $entry)
                        <li class="truncate">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $entry['student']->user?->name }}</span>
                            — {{ $entry['reason'] }}
                        </li>
                    @endforeach
                    @if (count($siswaPerluPerhatian) > 5)
                        <li class="text-gray-400">dan {{ count($siswaPerluPerhatian) - 5 }} siswa lainnya...</li>
                    @endif
                </ul>
            @endif
        </div>

        {{-- Informasi Wali Kelas --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Informasi Wali Kelas</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Anda bertindak sebagai wali untuk kelas <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $wali->classroom?->name ?? '-' }}</span>.
                Gunakan menu di samping untuk memeriksa rekapitulasi nilai akademik dan sikap seluruh siswa di kelas Anda.
            </p>
        </div>

        {{-- Grafik Distribusi Nilai --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Distribusi Nilai Akhir Siswa</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sebaran nilai akhir (semua mapel) siswa di kelas ini.</p>
            <div class="mt-4 h-64">
                <canvas id="chart-distribution" aria-label="Grafik distribusi nilai akhir"></canvas>
            </div>
        </div>
    </div>

    <script>
        const isDarkMode = () => document.documentElement.classList.contains('dark');
        const gridColor = () => (isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)');
        const tickColor = () => (isDarkMode() ? '#9ca3af' : '#6b7280');
        let distributionChart = null;
        let waliChartRetry = 0;
        function getWaliChart() {
            return window.Chart ?? null;
        }
        async function renderWaliCharts() {
            const canvas = document.getElementById('chart-distribution');
            if (!canvas) return;
            if (canvas.clientWidth === 0 || canvas.clientHeight === 0) {
                if (waliChartRetry < 12) { waliChartRetry++; requestAnimationFrame(() => { renderWaliCharts(); }); }
                return;
            }
            waliChartRetry = 0;
            const ChartLib = getWaliChart();
            if (!ChartLib) { if (waliChartRetry < 6) { waliChartRetry++; setTimeout(() => renderWaliCharts(), 180); } return; }
            if (distributionChart) { try { distributionChart.destroy(); } catch(e) {} distributionChart = null; }
            distributionChart = new ChartLib(canvas, {
                type: 'bar',
                data: {
                    labels: @json($chartDistribution['labels']),
                    datasets: [{
                        label: 'Jumlah Nilai',
                        data: @json($chartDistribution['data']),
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
        function scheduleWaliCharts() { requestAnimationFrame(() => { renderWaliCharts(); }); }
        document.addEventListener('DOMContentLoaded', scheduleWaliCharts);
        document.addEventListener('turbo:load', scheduleWaliCharts);
        document.addEventListener('turbo:render', scheduleWaliCharts);
        window.addEventListener('themechange', scheduleWaliCharts);
        window.addEventListener('load', scheduleWaliCharts);
        setTimeout(scheduleWaliCharts, 320);
    </script>
</x-layouts.wali_kelas>