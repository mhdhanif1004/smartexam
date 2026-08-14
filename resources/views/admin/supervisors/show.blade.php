<x-layouts.admin :title="'Detail Pengawas - '.($supervisor->user?->name ?? 'Pengawas')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $supervisor->user?->name }}</h2>
                    <x-badge-status :status="$supervisor->user?->is_active ? 'aktif' : 'nonaktif'" />
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $supervisor->user?->email }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.supervisors.edit', $supervisor) }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Edit</a>
                <a href="{{ route('admin.supervisors.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Riwayat Penugasan Ruangan</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Penugasan hasil rotasi pengawas pada setiap hari ujian dalam periode.</p>
                </div>
                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $supervisor->roomAssignments->count() }} penugasan</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hari</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sesi / Periode</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ruangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($assignmentsByDate as $group)
                            @foreach ($group['assignments'] as $assignment)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $group['dayLabel'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $group['dateLabel'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->examPeriod?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                            {{ $assignment->room?->display_name ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada penugasan ruangan untuk pengawas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
