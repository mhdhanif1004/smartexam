<x-layouts.guru_mapel title="Riwayat Nilai Siswa">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Riwayat Nilai — {{ $student->user?->name }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                NISN {{ $student->nisn }} &middot; {{ $subject?->name ?? 'Mata Pelajaran' }}
            </p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('guru_mapel.grades.students') }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                &larr; Kembali
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Semua Nilai</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $grades->count() }} catatan nilai.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Skor</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Catatan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($grades as $index => $grade)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-6 py-3 text-right text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $grade->score, 2) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $grade->note ?: '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $grade->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada nilai untuk siswa ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.guru_mapel>
