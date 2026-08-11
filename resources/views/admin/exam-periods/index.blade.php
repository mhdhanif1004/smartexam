<x-layouts.admin title="Sesi Ujian">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Sesi Ujian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola blok waktu ujian. Satu sesi bisa memuat banyak kelompok ruangan dan mata pelajaran sekaligus.</p>
            </div>
            <a href="{{ route('admin.exam-periods.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Sesi
            </a>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama Sesi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jam</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah Jadwal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($periods as $index => $period)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $period->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $period->exam_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ \Illuminate\Support\Str::substr($period->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($period->end_time, 0, 5) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $period->schedules_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.exam-periods.show', $period) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Kelola</a>
                                        <a href="{{ route('admin.exam-periods.groups.create', $period) }}" class="rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">Tambah Kelompok</a>
                                        <button type="button" @click="deleteUrl = '{{ route('admin.exam-periods.destroy', $period) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada sesi ujian. Klik "Tambah Sesi" untuk membuatnya.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>
