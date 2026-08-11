<x-layouts.admin :title="'Kelola - '.$examPeriod->name">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $examPeriod->name }}</h2>
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $examPeriod->schedules_count }} jadwal</span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $examPeriod->exam_date->format('d M Y') }} &middot; {{ \Illuminate\Support\Str::substr($examPeriod->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($examPeriod->end_time, 0, 5) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.exam-periods.groups.create', $examPeriod) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Kelompok Ruangan
                </a>
                <a href="{{ route('admin.exam-periods.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        @include('admin.partials.flash')

        @forelse ($examPeriod->schedules->groupBy(fn ($schedule) => $schedule->room?->name ?? 'Tanpa Ruangan') as $roomName => $schedules)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $roomName }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $schedules->first()?->class_name }} &middot; {{ $schedules->count() }} jadwal</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Waktu</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Durasi</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            @foreach ($schedules as $schedule)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $schedule->subject?->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $schedule->duration_minutes }} menit</td>
                                    <td class="px-4 py-3 text-sm">
                                        @php($computedStatus = $schedule->computedStatus())
                                        <x-badge-status :status="$computedStatus" :label="$statuses[$computedStatus] ?? $computedStatus" />
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.exam-schedules.edit', $schedule) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                                            <form method="POST" action="{{ route('admin.exam-schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus jadwal ini?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Belum ada jadwal ujian di sesi ini.</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Buat kelompok ruangan pertama dengan menekan tombol "Tambah Kelompok Ruangan".</p>
            </div>
        @endforelse
    </div>
</x-layouts.admin>
