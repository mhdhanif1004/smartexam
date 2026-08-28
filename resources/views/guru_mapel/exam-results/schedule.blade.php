<x-layouts.guru_mapel :title="'Hasil Ujian - '.($scheduleModel->subject?->name ?? 'Ujian')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Hasil Ujian CBT</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $scheduleModel->subject?->name }} &middot; {{ $scheduleModel->class_name ?? '-' }} &middot; {{ $scheduleModel->exam_date?->format('d M Y') }}
                </p>
            </div>
            <a href="{{ route('guru_mapel.exam-results.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Kembali</a>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Peserta Ujian</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $students->count() }} peserta</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">NISN</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nama</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Absensi</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Skor</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($students as $index => $student)
                            @php
                                $session = $sessions->get($student->id);
                                $result = $session ? $results->get($session->id) : null;
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->nisn }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-center text-sm">
                                    @if ($session?->attendance_status === \App\Models\ExamSession::ATTENDANCE_PRESENT)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Hadir</span>
                                    @elseif ($session?->attendance_status === \App\Models\ExamSession::ATTENDANCE_ABSENT)
                                        <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Tidak Hadir</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                    {{ $session ? (\App\Models\ExamSession::STATUSES[$session->status] ?? $session->status) : 'Belum mulai' }}
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-semibold {{ $result?->is_passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $result ? number_format((float) $result->total_score, 2) : '-' }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if ($session)
                                        <a href="{{ route('guru_mapel.exam-results.student', [$scheduleModel->id, $student->id]) }}"
                                           class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                            Detail Jawaban
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada peserta pada jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.guru_mapel>
