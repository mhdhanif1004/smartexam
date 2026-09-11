<x-layouts.wali_kelas title="Nilai Akademik">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Nilai Akademik</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Kelas <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $wali->classroom?->name ?? '-' }}</span> — nilai final per mata pelajaran (menggabungkan hasil CBT dan override guru mapel jika ada).
                    </p>
                </div>
                <x-semester-selector :semesters="$semesters" :selected-semester-id="$selectedSemesterId" />
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
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
</x-layouts.wali_kelas>