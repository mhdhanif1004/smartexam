<x-layouts.admin :title="'Roster - '.$room->display_name.' - '.$examPeriod->name">
    <style>
        @media print {
            aside, header { display: none !important; }
            main { padding: 0 !important; }
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .print-area { border: none !important; box-shadow: none !important; }
            tr { break-inside: avoid; }
        }
    </style>

    <div class="space-y-6">
        <div class="no-print flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Roster Peserta</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cetak daftar hadir untuk pengawas ruangan.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" />
                    </svg>
                    Cetak / Simpan PDF
                </button>
                <a href="{{ route('admin.exam-periods.show', $examPeriod) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        <div class="print-area overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $examPeriod->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $examPeriod->exam_date->format('d M Y') }} &middot; {{ \Illuminate\Support\Str::substr($examPeriod->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($examPeriod->end_time, 0, 5) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ruangan: {{ $room->display_name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $assignments->count() }} siswa</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">NISN</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama Siswa</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kelas</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kursi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanda Tangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($assignments as $assignment)
                            <tr class="align-top">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->student?->nisn }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment->student?->user?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->student?->class_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->seat_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="h-8"></div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada siswa ditempatkan di ruangan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
