<x-layouts.admin title="Master Data Kelas">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Master Data Kelas</h2>
                <p class="mt-1 text-sm text-gray-500">Kelola daftar kelas yang dipakai saat import siswa.</p>
            </div>
            <a href="{{ route('admin.classrooms.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Kelas
            </a>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.classrooms.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama kelas..." class="block w-full" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search'))
                    <a href="{{ route('admin.classrooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                @endif
            </div>
        </form>

        <x-table :headers="['No', 'Nama Kelas', 'Jumlah Siswa', 'Aksi']">
            @forelse ($classrooms as $index => $classroom)
                <tr class="transition hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $classrooms->firstItem() + $index }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $classroom->name }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center rounded-full {{ $classroom->students_count > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }} px-2.5 py-0.5 text-xs font-medium">{{ $classroom->students_count }} siswa</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.classrooms.edit', $classroom) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Edit</a>
                            <button type="button" @click="deleteUrl = '{{ route('admin.classrooms.destroy', $classroom) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Hapus</button>
                        </div>
                    </td>
                </tr>
            @empty
            @endforelse
        </x-table>

        <div>{{ $classrooms->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>
