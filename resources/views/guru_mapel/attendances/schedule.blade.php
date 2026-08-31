<x-layouts.guru_mapel :title="'Absensi Ujian - '.($scheduleModel->subject?->name ?? 'Ujian')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Absensi Ujian CBT</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $scheduleModel->subject?->name }} &middot; {{ $scheduleModel->class_name ?? '-' }} &middot; {{ $scheduleModel->exam_date?->format('d M Y') }}
                </p>
            </div>
            <a href="{{ route('guru_mapel.attendances.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Kembali</a>
        </div>

        @include('admin.partials.flash')

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Hadir</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $present }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/20 dark:bg-rose-500/10">
                <p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Tidak Hadir</p>
                <p class="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">{{ $absent }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Terkonfirmasi</p>
                <p class="mt-1 text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $confirmed }} <span class="text-sm font-normal text-gray-400 dark:text-gray-500">/ {{ count($students) }}</span></p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Peserta Ujian</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ count($students) }} peserta</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">NISN</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nama</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sesi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($students as $index => $student)
                            @php $session = $sessions->get($student->id); @endphp
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
                                <td class="px-6 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                    {{ $session ? (\App\Models\ExamSession::STATUSES[$session->status] ?? $session->status) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada peserta pada jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.guru_mapel>
