<x-layouts.admin :title="'Detail Guru Mapel - '.($guruMapel->user?->name ?? 'Guru Mapel')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $guruMapel->user?->name }}</h2>
                    <x-badge-status :status="$guruMapel->user?->is_active ? 'aktif' : 'nonaktif'" />
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $guruMapel->user?->email }}</p>
                @if ($guruMapel->nip)
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">NIP: {{ $guruMapel->nip }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.guru-mapels.edit', $guruMapel) }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Edit</a>
                <a href="{{ route('admin.guru-mapels.assignments.edit', $guruMapel) }}" class="inline-flex items-center rounded-lg border border-indigo-300 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 dark:border-indigo-600 dark:bg-gray-800 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Kelola Mapel</a>
                <a href="{{ route('admin.guru-mapels.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cakupan Mengajar (Mapel - Kelas)</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Mapel yang diampu guru beserta kelas target, diturunkan dari soal yang dibuat guru. Bersifat read-only (tidak diisi manual).</p>
                </div>
                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $assignments->count() }} pasangan</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kelas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($assignments as $index => $assignment)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment['subject']?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment['classroom']->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada cakupan mengajar untuk guru ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
