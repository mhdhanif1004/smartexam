<x-layouts.admin title="Ruangan Ujian">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Ruangan Ujian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola daftar ruangan yang digunakan untuk ujian.</p>
            </div>
            <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Ruangan
            </a>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.rooms.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama ruangan..." class="block w-full" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search'))
                    <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                @endif
            </div>
        </form>

        <x-table :headers="['No', 'Nama Ruangan', 'Kapasitas', 'Jumlah Siswa', 'Jumlah Pengawas', 'Jumlah Jadwal', 'Aksi']">
            @forelse ($rooms as $index => $room)
                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $rooms->firstItem() + $index }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $room->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $room->capacity }} peserta</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $room->students_count }} siswa</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $room->supervisors_count }} pengawas</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">{{ $room->exam_schedules_count }} jadwal</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.rooms.edit', $room) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                            <button type="button" @click="deleteUrl = '{{ route('admin.rooms.destroy', $room) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                        </div>
                    </td>
                </tr>
            @empty
            @endforelse
        </x-table>

        <div>{{ $rooms->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>
