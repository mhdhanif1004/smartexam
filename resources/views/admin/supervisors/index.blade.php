<x-layouts.admin title="Data Pengawas">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Data Pengawas</h2>
                <p class="mt-1 text-sm text-gray-500">Kelola data pengawas beserta penugasan ruangannya.</p>
            </div>
            <a href="{{ route('admin.supervisors.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Pengawas
            </a>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.supervisors.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, atau ruangan..." class="block w-full" />
            </div>
            <div>
                <select name="room" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-auto">
                    <option value="">Semua Ruangan</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(request('room') == $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('room'))
                    <a href="{{ route('admin.supervisors.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                @endif
            </div>
        </form>

        <x-table :headers="['No', 'Nama', 'Email', 'Password', 'Ruangan', 'Status', 'Aksi']">
            @forelse ($supervisors as $index => $supervisor)
                <tr class="transition hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $supervisors->firstItem() + $index }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $supervisor->user?->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $supervisor->user?->email }}</td>
                    @include('admin.partials.password-cell', ['user' => $supervisor->user])
                    <td class="px-4 py-3 text-sm text-gray-700">
                        @if ($supervisor->room)
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                {{ $supervisor->room->name }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Belum ditugaskan</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <x-badge-status :status="$supervisor->user?->is_active ? 'aktif' : 'nonaktif'" />
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.supervisors.edit', $supervisor) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Edit</a>
                            <button type="button" @click="deleteUrl = '{{ route('admin.supervisors.destroy', $supervisor) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Hapus</button>
                        </div>
                    </td>
                </tr>
            @empty
            @endforelse
        </x-table>

        <div>{{ $supervisors->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>
