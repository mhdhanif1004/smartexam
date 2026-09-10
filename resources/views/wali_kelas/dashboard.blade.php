<x-layouts.wali_kelas title="Dashboard Wali Kelas">
    <div class="space-y-6" x-data="{ tab: '{{ request()->query('tab', 'overview') }}' }">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Dashboard Wali Kelas</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pantau kelas yang Anda ampu sebagai wali kelas.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kelas</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $wali->classroom?->name ?? 'Belum ditugaskan' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Jumlah Siswa</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $studentCount }}</p>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-800">
            <nav class="-mb-px flex flex-wrap items-center gap-x-6 gap-y-1" aria-label="Tabs">
                <button
                    type="button"
                    @click="tab = 'overview'"
                    :class="tab === 'overview' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition"
                >
                    Ringkasan Kelas
                </button>
                <button
                    type="button"
                    @click="tab = 'grades'"
                    :class="tab === 'grades' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition"
                >
                    Nilai Akademik (Read-Only)
                </button>
                <button
                    type="button"
                    @click="tab = 'attitude'"
                    :class="tab === 'attitude' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition"
                >
                    Nilai Sikap
                </button>
                <button
                    type="button"
                    @click="tab = 'violations'"
                    :class="tab === 'violations' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition"
                >
                    Rekap Pelanggaran
                </button>
                <button
                    type="button"
                    @click="tab = 'catatan'"
                    :class="tab === 'catatan' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition"
                >
                    Catatan Wali Kelas
                </button>
            </nav>
        </div>

        <!-- Tab 1: Overview -->
        <div x-show="tab === 'overview'" class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Informasi Wali Kelas</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Anda bertindak sebagai wali untuk kelas <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $wali->classroom?->name ?? '-' }}</span>.
                    Gunakan tab di atas untuk memeriksa rekapitulasi nilai akademik dan sikap seluruh siswa di kelas Anda.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            </div>
        </div>

        <!-- Tab 2: Academic Grades (Read-Only) -->
        <div x-show="tab === 'grades'" x-cloak class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Rekapitulasi Nilai Akademik Siswa</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan nilai final per mata pelajaran (menggabungkan hasil CBT dan override guru mapel jika ada).</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NISN / Nama Siswa</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nilai Akhir</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sumber</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($academicGrades as $data)
                                @php
                                    $student = $data['student'];
                                    $gradesBySubject = $data['grades'];
                                    $firstSubject = true;
                                    $subjectCount = count($gradesBySubject);
                                @endphp

                                @foreach ($gradesBySubject as $subjectId => $gradeData)
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        @if ($firstSubject)
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100 align-top" rowspan="{{ max(1, $subjectCount) }}">
                                                <div class="font-bold">{{ $student->user?->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">NISN: {{ $student->nisn ?? '-' }}</div>
                                            </td>
                                            @php $firstSubject = false; @endphp
                                        @endif

                                        <td class="px-6 py-4 text-sm font-semibold text-gray-800 dark:text-gray-200">
                                            {{ $gradeData['subject']->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100">
                                            @if ($gradeData['score'] !== null)
                                                <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-sm font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                    {{ number_format($gradeData['score'], 2) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 italic">Belum ada nilai</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            @if ($gradeData['source'] === 'override')
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300" title="Nilai ini telah dikoreksi/override manual oleh guru mapel">
                                                    Override Guru
                                                </span>
                                            @elseif ($gradeData['source'] === 'auto')
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                    Terhitung Otomatis
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                            @if (!empty($gradeData['note']))
                                                <p class="mt-1 text-xs text-gray-500 italic">Catatan: {{ $gradeData['note'] }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada data nilai akademik untuk siswa di kelas ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Attitude Grades -->
        <div x-show="tab === 'attitude'" x-cloak class="space-y-6">
            @include('wali_kelas.attitude-grades.partials._tab', $attitudeGrades)
        </div>

        <!-- Tab 4: Rekap Pelanggaran -->
        <div x-show="tab === 'violations'" x-cloak class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Rekap Pelanggaran Per Siswa</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan pelanggaran dari seluruh sesi ujian di kelas ini.</p>
                </div>

                @if ($totalViolations === 0)
                    <div class="px-6 py-16 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Tidak ada pelanggaran tercatat</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelas ini belum memiliki pelanggaran dari sesi ujian manapun.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/60">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nama Siswa</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Rincian Tipe</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ditangani</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @php $index = 0; @endphp
                                @foreach ($violationsByStudent as $vData)
                                    @php
                                        $vIndex = $index++;
                                        $vStudent = $vData['student'];
                                        $vTotal = $vData['total'];
                                        $vTypes = $vData['types'];
                                        $vHandled = $vData['handled'];
                                        $vUnhandled = $vData['unhandled'];
                                    @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $vIndex + 1 }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $vStudent->user?->name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">NISN: {{ $vStudent->nisn ?? '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm">
                                            <span class="inline-flex items-center justify-center min-w-[2rem] rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700 dark:bg-red-500/10 dark:text-red-300">
                                                {{ $vTotal }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            @forelse ($vTypes as $type => $count)
                                                <span class="mr-2 inline-block">
                                                    <span class="text-gray-600 dark:text-gray-400">{{ \App\Models\Violation::typeLabel($type) }}</span>
                                                    <span class="ml-1 font-semibold text-gray-900 dark:text-gray-100">×{{ $count }}</span>
                                                </span>
                                            @empty
                                                <span class="text-gray-400 italic">-</span>
                                            @endforelse
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm">
                                            @if ($vUnhandled > 0)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                                    {{ $vUnhandled }} belum ditangani
                                                </span>
                                            @elseif ($vTotal > 0)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                    Semua ditangani
                                                </span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Tab 5: Catatan Wali Kelas -->
        <div x-show="tab === 'catatan'" x-cloak class="space-y-6">
            @include('wali_kelas.catatan.index', $catatan ?? [])
        </div>
    </div>
</x-layouts.wali_kelas>