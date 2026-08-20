<x-layouts.admin :title="'Kelola - '.$examPeriod->name">
    <div x-data="{
        openRooms: {},
        toggleRoom(id) {
            this.openRooms[id] = !this.openRooms[id];
        },
        isOpen(id) {
            return Boolean(this.openRooms[id]);
        },
    }" class="space-y-6">
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
                <form method="POST" action="{{ route('admin.exam-periods.supervisor-rotation', $examPeriod) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Generate Rotasi Pengawas
                    </button>
                </form>
                <a href="{{ route('admin.exam-periods.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        @include('admin.partials.flash')

        @if ($roomGroups->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Override Pengawas per Ruangan</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Atur jumlah pengawas khusus per ruangan untuk sesi ini. Kosongkan atau isi 0 untuk memakai default dari pengaturan ruangan.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.exam-periods.room-overrides.update', $examPeriod) }}">
                    @csrf
                    @method('PUT')
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ruangan</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Default</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Override (0 = pakai default)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @foreach ($roomGroups as $group)
                                    @php
                                        $room = $group['room'];
                                        $override = $examPeriod->roomOverrides->firstWhere('room_id', $room?->id);
                                    @endphp
                                    @if ($room)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $room->display_name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $room->supervisor_count }} pengawas</td>
                                            <td class="px-4 py-3">
                                                <input type="hidden" name="overrides[{{ $loop->index }}][room_id]" value="{{ $room->id }}">
                                                <input type="number" name="overrides[{{ $loop->index }}][supervisor_count]"
                                                    min="0" max="10"
                                                    value="{{ $override?->supervisor_count ?? 0 }}"
                                                    class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                                    placeholder="0">
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end border-t border-gray-200 px-5 py-3 dark:border-gray-800">
                        <x-primary-button>Simpan Override</x-primary-button>
                    </div>
                </form>
            </div>
        @endif

        @forelse ($roomGroups as $group)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                    <button type="button" @click="toggleRoom(@js($group['room']?->id))" class="flex min-w-0 flex-1 items-center justify-between gap-3 text-left">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $group['room']?->display_name ?? 'Tanpa Ruangan' }}</h3>
                            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $group['schedules']->count() }} jadwal</span>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $group['assignments']->count() }} siswa</span>
                            @forelse ($group['supervisors'] as $supervisor)
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Pengawas: {{ $supervisor->user?->name }}</span>
                            @empty
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700/60 dark:text-gray-400">Belum ada pengawas</span>
                            @endforelse
                        </div>
                        <span class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="max-w-[16rem] truncate">{{ $group['schedules']->first()?->class_name }}</span>
                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="isOpen(@js($group['room']?->id)) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </span>
                    </button>
                    @if ($group['room'])
                        <a href="{{ route('admin.exam-periods.room-roster', [$examPeriod, $group['room']]) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" />
                            </svg>
                            Cetak Roster
                        </a>
                    @endif
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
                            @foreach ($group['schedules'] as $schedule)
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
                <div x-show="isOpen(@js($group['room']?->id))" x-transition x-cloak class="border-t border-gray-200 dark:border-gray-800">
                    <div class="flex items-center justify-between px-5 py-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Daftar Peserta</h4>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $group['assignments']->count() }} siswa</span>
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
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($group['assignments'] as $assignment)
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->student?->nisn }}</td>
                                        <td class="px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment->student?->user?->name }}</td>
                                        <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->student?->class_name }}</td>
                                        <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->seat_number }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada siswa ditempatkan di ruangan ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
