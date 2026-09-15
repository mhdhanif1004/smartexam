<x-layouts.guru_mapel title="Jadwal Ujian Saya">
    <div class="space-y-6" x-data="{
        deleteUrl: '',
        deleteName: '',
        deleteMode: 'simple',
        deleteHasConfirmed: false,
        openDelete(url, name, mode, hasConfirmed) {
            this.deleteUrl = url;
            this.deleteName = name;
            this.deleteMode = mode;
            this.deleteHasConfirmed = hasConfirmed;
        }
    }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Jadwal Ujian Saya</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Jadwal ujian yang Anda buat untuk mapel-kelas yang Anda ampu.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('guru_mapel.exam-schedules.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Buat Jadwal Ujian
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sesi Ujian</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kelas</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Waktu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($periods as $index => $period)
                            @php
                                $firstSchedule = $period->schedules->first();
                                $liveStatus = $firstSchedule?->computedStatus() ?? ExamSchedule::STATUS_SCHEDULED;
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $periods->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $period->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $period->examType?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $firstSchedule?->classroom?->name ?? $firstSchedule?->class_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ \Illuminate\Support\Carbon::parse($period->exam_date->toDateString().' '.$period->start_time)->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <x-badge-status :status="$liveStatus" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('guru_mapel.exam-schedules.edit', $period) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                                        @if ($liveStatus !== 'finished')
                                            <a href="{{ route('guru_mapel.proctor.show', $period) }}" class="rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">Awasi Sesi Ini</a>
                                        @endif
                                        <button type="button"
                                                @click="openDelete('{{ route('guru_mapel.exam-schedules.destroy', $period) }}', '{{ $period->name }}', '{{ $deleteTiers[$period->id]['mode'] }}', {{ $deleteTiers[$period->id]['has_confirmed_attendance'] ? 'true' : 'false' }}); $dispatch('open-modal', 'confirm-delete-schedule')"
                                                class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada jadwal ujian yang Anda buat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $periods->links() }}</div>

        <x-modal name="confirm-delete-schedule" maxWidth="sm">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-500/10">
                        <svg class="h-5 w-5 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Hapus Jadwal Ujian</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Yakin ingin menghapus <span class="font-bold" x-text="deleteName"></span>?
                        </p>
                    </div>
                </div>

                <div x-show="deleteMode === 'blocked'" x-transition class="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-500/10 dark:text-rose-300">
                    Sesi ujian sudah mulai dikerjakan siswa — tidak dapat dihapus. Histori pengerjaan & nilai siswa tidak boleh dimusnahkan.
                </div>

                <div x-show="deleteMode === 'warning'" x-transition class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                    <b>Data absensi yang sudah dikonfirmasi akan ikut terhapus.</b> Pastikan tidak ada peserta yang sedang/akan mengerjakan ujian ini.
                </div>

                <div x-show="deleteMode !== 'blocked'" class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <form method="POST" :action="deleteUrl" class="inline">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Hapus Jadwal</x-danger-button>
                    </form>
                </div>

                <div x-show="deleteMode === 'blocked'" class="mt-6 flex justify-end">
                    <x-secondary-button x-on:click="$dispatch('close')">Tutup</x-secondary-button>
                </div>
            </div>
        </x-modal>
    </div>
</x-layouts.guru_mapel>